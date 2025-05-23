<?php

namespace app\api\controller;

use app\admin\model\Company;
use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\JobMajor;
use app\admin\model\JobNiceSkill;
use app\admin\model\JobSkill;
use app\admin\model\Resume;
use app\admin\model\SendLog;
use app\admin\model\Subscribe;
use app\admin\model\University;
use app\api\basic\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use support\Db;
use support\Log;
use support\Request;
use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;
use Webman\RateLimiter\Limiter;
use Webman\RateLimiter\RateLimitException;
use app\admin\model\User;

/**
 * 求职端
 */
class ResumeController extends Base
{

    protected $noNeedLogin = ['index', 'detail', 'indexSearch', 'getHotKeyWord'];

    #首页
    function index(Request $request)
    {
        $salary = $request->post('salary', ''); #薪资范围 0=50,000 以下 1=50000 - 80000 2=80000 - 120000 3=120000 - 160000 4=160000 - 200000 5=200000 以上
        $eligible = $request->post('eligible', '');#是否认证HR 0否 1是
        $province = $request->post('province', '');#所属州
        $position_type = $request->post('position_type', '');#工作类型
        $work_mode = $request->post('work_mode', '');#工作模式:0=In-Person=现场办公,1=Hybrid=混合办公,2=Remote=远程办公
        $keyword =$request->post('keyword', '');#关键词
        $resume_id = $request->post('resume_id', '');
        try {
            $request->user_id = JwtToken::getCurrentId();
        } catch (JwtTokenException $e) {
            $request->user_id = 0;
        }
        $company = Company::where('name', $keyword)->first();
        if (!empty($company)) {
            $subscribeStatus = Subscribe::where(['user_id' => $request->user_id, 'company_name' => $company->name])->first();
            $company->setAttribute('is_subscribe', $subscribeStatus ? 1 : 0);
        }


        #指定简历
        $resume = Resume::where(['user_id' => $request->user_id, 'id' => $resume_id])->first();#默认简历

        $query = Job::where(['status' => 1])
            ->with(['user'])
            ->orderBy(
                User::select('online')
                    ->whereColumn('id', 'wa_job.user_id'),
                'desc'
            )
            //薪资范围筛选
            ->when(!empty($salary) || $salary == 0, function (Builder $query) use ($salary) {
                if ($salary == 0) {
                    $query->where('minimum_salary', '<', 50000);
                }
                if ($salary == 1) {
                    $query->whereBetween('minimum_salary', [50000, 80000]);
                }
                if ($salary == 2) {
                    $query->whereBetween('minimum_salary', [80000, 120000]);
                }
                if ($salary == 3) {
                    $query->whereBetween('minimum_salary', [120000, 160000]);
                }
                if ($salary == 4) {
                    $query->whereBetween('minimum_salary', [160000, 200000]);
                }
                if ($salary == 5) {
                    $query->where('minimum_salary', '>', 200000);
                }
            })
            //关键字搜索筛选
            ->when(!empty($keyword), function (Builder $query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('position_name', $keyword)
                        ->orWhereHas('user', function (Builder $query) use ($keyword) {
                            $query->where('company_name', $keyword);
                        });
                });
            })
            //认证HR筛选
            ->when(!empty($eligible), function (Builder $query) {
                $query->whereHas('user', function (Builder $query) {
                    $query->where('hr_type', '>=', 2);
                });
            })
            //所属州筛选
            ->when(!empty($province), function (Builder $query) use ($province) {
                $query->where('position_location', $province);
            })
            //工作类型筛选
            ->when(!empty($position_type), function (Builder $query) use ($position_type) {
                $query->where('position_type', $position_type);
            })
            //工作模式筛选
            ->when(!empty($work_mode) || $work_mode == 0, function (Builder $query) use ($work_mode) {
                $query->where('work_mode', $work_mode);
            });
        if (!empty($resume)) {
            //如果有指定简历
            $fulltimeSkill = $resume->fulltimeSkill->pluck('name')->toArray(); #全职技能
            $internshipSkill = $resume->internshipSkill->pluck('name')->toArray();#实习技能
            $projectSkill = $resume->projectSkill->pluck('name')->toArray();#项目技能
            $skill = $resume->skill->pluck('name')->toArray();#技术栈
            $query = $query
                //技术栈筛选
                ->when(function (Builder $query) {
                    return $query->has('skill');
                }, function (Builder $query) use ($skill) {
                    $query->whereDoesntHave('skill', function ($query) use ($skill) {
                        $query->whereNotIn('name', $skill);
                    });
                })
                //学历筛选
                ->where(function ($query) use ($resume) {
                    // 主查询条件
                    $query->whereExists(function ($subQuery) use ($resume) {
                        // 子查询处理学历匹配逻辑
                        $subQuery->select(DB::raw(1))
                            ->from('wa_educational_background as edu')
                            ->where('edu.resume_id', $resume->id)
                            ->where(function ($q) {
                                // 学历要求匹配
                                $q->whereColumn('edu.degree_to_job', 'wa_job.degree_requirements')
                                    // GPA要求
                                    ->where('edu.cumulative_gpa', '>=', DB::raw('wa_job.overall_gpa_requirement'))
                                    ->where('edu.major_gpa', '>=', DB::raw('wa_job.major_gpa_requirement'))
                                    // 排名要求
                                    ->where(function ($rankQ) {
                                        $rankQ->whereRaw('(wa_job.degree_qs_ranking = 0 OR (edu.top_qs_ranking <= wa_job.degree_qs_ranking AND edu.top_qs_ranking != 0))')
                                            ->whereRaw('(wa_job.degree_us_ranking = 0 OR (edu.top_us_ranking <= wa_job.degree_us_ranking AND edu.top_us_ranking != 0))');
                                    })
                                    // 专业匹配
                                    ->where(function ($majorQ) {
                                        $majorQ->whereNotExists(function ($noMajor) {
                                            $noMajor->select(DB::raw(1))
                                                ->from('wa_job_major')
                                                ->whereColumn('wa_job_major.job_id', 'wa_job.id');
                                        })
                                            ->orWhereExists(function ($hasMajor) {
                                                $hasMajor->select(DB::raw(1))
                                                    ->from('wa_job_major')
                                                    ->whereColumn('wa_job_major.job_id', 'wa_job.id')
                                                    ->whereColumn('wa_job_major.name', 'edu.major');
                                            });
                                    });
                            });
                    })->orWhere(function ($q) use ($resume) {
                        // 处理特殊情况的否定条件
                        $q->whereRaw('wa_job.degree_requirements < ?', [$resume->top_degree])
                            ->where(function ($sub) {
                                $sub->where('wa_job.overall_gpa_requirement','>', 0)
                                    ->where('wa_job.major_gpa_requirement','>', 0)
                                    ->where('wa_job.degree_qs_ranking','>', 0)
                                    ->where('wa_job.degree_us_ranking','>', 0);
                            });
                    });
                })
                //项目技术栈匹配
                ->when(function (Builder $query) {
                    return $query->value('project_tech_stack_match') == 1;
                }, function (Builder $query) use ($projectSkill) {
                    $query->whereDoesntHave('skill', function ($query) use ($projectSkill) {
                        $query->whereNotIn('name', $projectSkill);
                    });
                })
                //实习技术栈匹配
                ->when(function (Builder $query) {
                    return $query->value('internship_tech_stack_match') == 1;
                }, function (Builder $query) use ($internshipSkill) {
                    $query->whereDoesntHave('skill', function ($query) use ($internshipSkill) {
                        $query->whereNotIn('name', $internshipSkill);
                    });
                })
                //全职技术栈匹配
                ->when(function (Builder $query) {
                    return $query->value('full_time_tech_stack_match') == 1;
                }, function (Builder $query) use ($fulltimeSkill) {
                    $query->whereDoesntHave('skill', function ($query) use ($fulltimeSkill) {
                        $query->whereNotIn('name', $fulltimeSkill);
                    });
                })
                //全职工作最低年限要求
                ->when(function (Builder $query) {
                    return $query->value('minimum_full_time_internship_experience_years') > 0;
                }, function (Builder $query) use ($resume) {
                    $query->where('minimum_full_time_internship_experience_years', '<=', $resume->total_full_time_experience_years);
                })
                //实习工作最低段数要求
                ->when(function (Builder $query) {
                    return $query->value('minimum_internship_experience_number') > 0;
                }, function (Builder $query) use ($resume) {
                    $query->where('minimum_internship_experience_number', '<=', $resume->total_internship_experience_number);
                })
                ->when(function (Builder $query) {
                    return $query->value('graduation_date') != null;
                }, function (Builder $query) use ($resume) {
                    $query->where('graduation_date', $resume->end_graduation_date);
                })
                // 应届生毕业日期
                ->where(function (Builder $query) use ($resume) {
                    $query->where(function (Builder $subQuery) use ($resume) {
                        $subQuery->whereNotNull('graduation_date')
                            ->where('graduation_date', '>=', $resume->end_graduation_date);
                    })->orWhereNull('graduation_date');
                })

                // 是否允许已申请用户重复申请:0=false,1=true
                ->whereDoesntHave('sendLog', function (Builder $logQuery) use ($request) {
                    $logQuery->where('resume_user_id', $request->user_id);
                })

                //非必备技能排序
                ->when(function (Builder $query) {
                    return JobNiceSkill::where('job_id', $query->value('id'))->exists();
                }, function (Builder $query) use ($skill) {
                    $query->withCount(['niceSkill' => function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    }])
                        ->latest('nice_skill_count');
                });

            $user = User::find($request->user_id); #个人信息
            if (!empty($user) && !empty($user->profile)) {
                $top_secret = $user->profile->top_secret;#绝密权限
                $adult = $user->profile->adult;#是否成人
                $sponsorship = $user->profile->sponsorship;#是否签证支持
                $from_limitation = $user->profile->from_limitation;#受限国家
                $us_citizen = $user->profile->us_citizen;#美国公民
                $query = $query
                    //绝密权限
                    ->when($top_secret == 0, function (Builder $query) {
                        $query->where('top_secret', 0);
                    })

                    //是否成人
                    ->when($adult == 0, function (Builder $query) {
                        $query->where('adult', 0);
                    })

                    //是否签证支持
                    ->when($sponsorship == 0, function (Builder $query) use ($sponsorship) {
                        $query->where('sponsorship', 0);
                    })

                    //受限国家
                    ->when($from_limitation == 1, function (Builder $query) use ($from_limitation) {
                        $query->where('from_limitation', 1);
                    })

                    //是否美国公民
                    ->when($us_citizen == 0, function (Builder $query) use ($us_citizen) {
                        $query->where('us_citizen', 0);
                    });
            }
        }

        $query = $query
            //简历更新时间排序
            ->orderByDesc('updated_at');
        $rows = $query->paginate();

        return $this->success('成功', ['list' => $rows, 'company' => $company]);
    }

    #投递简历
    function send(Request $request)
    {
        $job_id = $request->post('job_id', 0);
        $resume_id = $request->post('resume_id', 0);
        $job = Job::find($job_id);
        $resume = Resume::find($resume_id);
        if (!$resume) {
            return $this->fail('请先选择简历');
        }
        if (!$job) {
            return $this->fail('岗位不存在');
        }
        if ($job->status == 0) {
            return $this->fail('岗位消失了');
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->fail('用户不存在');
        }

        //vip功能
        if (!$user->vip_status && $user->sendLog()->whereDate('created_at', Carbon::today())->count() >= 3) {
            return $this->fail('您今⽇的岗位投递次数已达上限，请充值 VIP 以解锁⽆限投递权限。');
        }

        if (empty($user->profile)) {
            return $this->fail('请先完善个人资料');
        }

        /**
         * 岗位保密
         */
        if ($job->top_secret == 1 && $user->profile->top_secret == 0) {
            return $this->fail('此岗位为绝密岗位');
        }
        if ($job->adult == 1 && $user->profile->adult == 0) {
            return $this->fail('此岗位为成年岗位');
        }

        if ($user->profile->sponsorship == 1 && $job->sponsorship == 0) {
            return $this->fail('此岗位不担保签证');
        }

        if ($job->from_limitation == 0 && $user->profile->from_limitation == 1) {
            return $this->fail('此岗位不接受受限国家');
        }

        if ($job->us_citizen == 1 && $user->profile->us_citizen == 0) {
            return $this->fail('此岗位仅限美国公民');
        }


        #岗位技术栈匹配
        $jobSkills = $job->skill->pluck('name');#岗位的技术栈
        $resumeSkills = $resume->skill->pluck('name');#简历的技术栈
        // 判断 job_skills 中的所有技能是否全部在 resume_skills 中
        $allSkillsMatch = $jobSkills->every(function ($skill) use ($resumeSkills) {
            return $resumeSkills->contains($skill);
        });
        if (!$allSkillsMatch) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求1');
        }
        #学历匹配
        $degreeRequirements = $job->degree_requirements;
        if (in_array($degreeRequirements, $resume->educationalBackground->pluck('degree_to_job')->toArray())) {
            $overallGpaRequirement = $job->overall_gpa_requirement;
            $majorGpaRequirement = $job->major_gpa_requirement;
            $degreeQsRanking = $job->degree_qs_ranking;
            $degreeUsRanking = $job->degree_us_ranking;
            if (empty($job->major->pluck('name')->toArray())) {
                $majorExists = false;
            } else {
                $majorExists = true;
            }
            // 筛选出符合的教育背景
            $filteredEducationalBackground = $resume->educationalBackground->filter(function (EducationalBackground $item) use ($degreeRequirements, $overallGpaRequirement, $majorGpaRequirement, $degreeQsRanking, $degreeUsRanking, $majorExists, $job) {
                $qsCondition = ($degreeQsRanking == 0) || ($item->top_qs_ranking <= $degreeQsRanking && $item->top_qs_ranking != 0);
                $usCondition = ($degreeUsRanking == 0) || ($item->top_us_ranking <= $degreeUsRanking && $item->top_us_ranking != 0);
                if ($majorExists) {
                    $majorCondition = in_array($item->major, $job->major->pluck('name')->toArray());
                } else {
                    $majorCondition = true;
                }
                return $item->degree_to_job == $degreeRequirements &&
                    $item->cumulative_gpa >= $overallGpaRequirement &&
                    $majorCondition &&
                    $item->major_gpa >= $majorGpaRequirement &&
                    $qsCondition &&
                    $usCondition;
            });
            if ($filteredEducationalBackground->isEmpty()) {
                return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求2');
            }
        } else {
            // 不符合
            if ($resume->top_degree > $degreeRequirements) {
                $overallGpaRequirement = $job->overall_gpa_requirement;
                $majorGpaRequirement = $job->major_gpa_requirement;
                $degreeQsRanking = $job->degree_qs_ranking;
                $degreeUsRanking = $job->degree_us_ranking;
                if ($overallGpaRequirement != 0 || $majorGpaRequirement != 0 || $degreeQsRanking != 0 || $degreeUsRanking != 0) {
                    return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求3');
                }
            } else {
                return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求4');
            }
        }

        //项目技术栈匹配
        if ($job->project_tech_stack_match == 1) {
            $jobSkills = $job->skill->pluck('name');
            $projectSkills = $resume->projectSkill->pluck('name');
            $allSkillsMatch = $jobSkills->every(function ($skill) use ($projectSkills) {
                return $projectSkills->contains($skill);
            });
            if (!$allSkillsMatch) {
                return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求5');
            }
        }

        //实习技术栈匹配
        if ($job->internship_tech_stack_match == 1) {
            $jobSkills = $job->skill->pluck('name');
            $internshipSkills = $resume->internshipSkill->pluck('name');
            $allSkillsMatch = $jobSkills->every(function ($skill) use ($internshipSkills) {
                return $internshipSkills->contains($skill);
            });
            if (!$allSkillsMatch) {
                return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求6');
            }
        }

        //全职技术栈匹配
        if ($job->full_time_tech_stack_match == 1) {
            $jobSkills = $job->skill->pluck('name');
            $fulltimeSkills = $resume->fulltimeSkill->pluck('name');
            $allSkillsMatch = $jobSkills->every(function ($skill) use ($fulltimeSkills) {
                return $fulltimeSkills->contains($skill);
            });
            if (!$allSkillsMatch) {
                return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求7');
            }
        }

        //全职工作最低年限要求
        if ($resume->total_full_time_experience_years < $job->minimum_full_time_internship_experience_years) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求8');
        }

        //实习工作最低段数要求
        if ($resume->total_internship_experience_number < $job->minimum_internship_experience_number) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求9');
        }

        //应届生毕业日期
        if (!empty($job->graduation_date) && $resume->end_graduation_date != $job->graduation_date) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求10');
        }

        //是否允许已申请用户重复申请
        if ($job->allow_duplicate_application == 0 && $resume->sendLog()->where('job_id', $job->id)->count() > 0) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求11');
        }


        SendLog::updateOrCreate(
            [
                'resume_user_id' => $request->user_id,
                'resume_id' => $resume->id,
                'job_id' => $job_id,
                'job_user_id' => $job->user_id,
            ],
            [
                'resume_user_id' => $request->user_id,
                'resume_id' => $resume->id,
                'job_id' => $job_id,
                'job_user_id' => $job->user_id,
            ]
        );
        #达到招聘人数自动下架
        if ($job->expected_number_of_candidates !== null && $job->sendLog()->count() >= $job->expected_number_of_candidates) {
            $job->status = 0;
            $job->save();
        }
        return $this->success('成功');
    }


    #详情
    function detail(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::with(['user'])->find($job_id);
        try {
            $request->user_id = JwtToken::getCurrentId();
        } catch (JwtTokenException $e) {
            $request->user_id = 0;
        }
        $row->setAttribute('is_send',$row->sendLog()->where('resume_user_id',$request->user_id)->exists());
        return $this->success('成功', $row);
    }


    #获取简历列表
    function getResumeList(Request $request)
    {
        $rows = Resume::with(['skill', 'user'])->where(['user_id' => $request->user_id])->get();
        return $this->success('成功', $rows);
    }

    #获取简历详情
    function getResumeDetail(Request $request)
    {
        $resume_id = $request->post('resume_id');
        $row = Resume::with([
            'educationalBackground' => function ($query) {
                $query->with(['university']);
            },
            'fullTimeExperience' => function ($query) {
                $query->with(['skill']);
            },
            'projectExperience' => function ($query) {
                $query->with(['skill']);
            },
            'internshipExperience' => function ($query) {
                $query->with(['skill']);
            },
            'skill'
        ])->find($resume_id);
        return $this->success('成功', $row);
    }

    function setDefaultResume(Request $request)
    {
        $resume_id = $request->post('resume_id');
        $row = Resume::find($resume_id);
        if (empty($row)) {
            return $this->fail('简历不存在');
        }
        Resume::where(['user_id' => $request->user_id])->where('id', '<>', $resume_id)->update(['default' => 0]);
        $row->default = 1;
        $row->save();
        return $this->success('成功');
    }

    #清空默认简历
    function clearDefaultResume(Request $request)
    {
        Resume::where(['user_id' => $request->user_id])->update(['default' => 0]);
        return $this->success('成功');
    }

    #获取默认简历
    function getDefaultResume(Request $request)
    {
        $row = Resume::where(['user_id' => $request->user_id, 'default' => 1])->first();
        return $this->success('成功', $row);
    }


    #创建简历
    function createResume(Request $request)
    {
        try {
            #限流器 每个用户1秒内只能请求1次
            Limiter::check('user_' . $request->user_id, 1, 10);
        } catch (RateLimitException $e) {
            return $this->fail('请求频繁');
        }
        $name = $request->post('name');#简历名称
        $file = $request->post('file');#简历附件
        $educational_background = $request->post('educational_background');#教育背景
        $project_experience = $request->post('project_experience');#项目背景
        $full_time_experience = $request->post('full_time_experience');#全职背景
        $internship_experience = $request->post('internship_experience');#实习背景
        $skill = $request->post('skill');#技术栈[{"name":"xxx"}]

        if (empty($file)) {
            return $this->fail('请上传简历附件');
        }
        $user = User::find($request->user_id);
        if (empty($user)) {
            return $this->fail('用户不存在');
        }
        $resume_count = $user->resume()->count();
        if ($user->vip_status) {
            if ($resume_count >= 5) {
                return $this->fail('简历数量已达上限');
            }
        } else {
            if ($resume_count >= 1) {
                return $this->fail('您当前为普通候选⼈，如需上传更多简历，请开通 VIP 会员。”');
            }
        }


        $row = Resume::where(['user_id' => $request->user_id, 'name' => $name])->first();
        if ($row) {
            return $this->fail('简历名称不能重复');
        }

        DB::connection('plugin.admin.mysql')->beginTransaction();
        try {
            $resume = Resume::create([
                'user_id' => $request->user_id,
                'name' => $name,
                'file' => $file,
            ]);
            // 映射岗位学历要求
            $degreeMapping = [
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 2,
                4 => 3,
                5 => 3,
                6 => 4,
                7 => 4,
            ];
            $top_degree = new Collection();
            foreach ($educational_background as $experience) {
                $cumulative_gpa = $experience['cumulative_gpa'] ?? 0;

                if ($cumulative_gpa > 4 || $cumulative_gpa < 0) {
                    throw new \Exception("总绩点必须在0-4之间");
                }
                $enrollment_date = $experience['enrollment_date'];
                $graduation_date = $experience['graduation_date'];
                if (strtotime($graduation_date) <= strtotime($enrollment_date)) {
                    throw new \Exception("毕业时间必须大于入学时间");
                }

                $university = University::find($experience['university_id']);
                if (!$university) {
                    throw new \Exception('学校不存在');
                }
                $qs_ranking = $university->qs_ranking;
                $us_ranking = $university->us_ranking;
                // 获取映射后的值，如果不存在则使用原始值
                $degreeToJob = $degreeMapping[$experience['degree']] ?? $experience['degree'];
                $top_degree->push($degreeToJob);
                if ($qs_ranking == 0) {
                    $top_qs_ranking = 0;
                } elseif ($qs_ranking <= 10) {
                    $top_qs_ranking = 1;
                } elseif ($qs_ranking <= 30) {
                    $top_qs_ranking = 2;
                } elseif ($qs_ranking <= 50) {
                    $top_qs_ranking = 3;
                } elseif ($qs_ranking <= 70) {
                    $top_qs_ranking = 4;
                } elseif ($qs_ranking <= 100) {
                    $top_qs_ranking = 5;
                } elseif ($qs_ranking <= 150) {
                    $top_qs_ranking = 6;
                } elseif ($qs_ranking <= 200) {
                    $top_qs_ranking = 7;
                } else {
                    $top_qs_ranking = 0;
                }
                if ($us_ranking == 0) {
                    $top_us_ranking = 0;
                } elseif ($us_ranking <= 10) {
                    $top_us_ranking = 1;
                } elseif ($us_ranking <= 30) {
                    $top_us_ranking = 2;
                } elseif ($us_ranking <= 50) {
                    $top_us_ranking = 3;
                } elseif ($us_ranking <= 70) {
                    $top_us_ranking = 4;
                } elseif ($us_ranking <= 100) {
                    $top_us_ranking = 5;
                } elseif ($us_ranking <= 150) {
                    $top_us_ranking = 6;
                } elseif ($us_ranking <= 200) {
                    $top_us_ranking = 7;
                } else {
                    $top_us_ranking = 0;
                }
                $experience['degree_to_job'] = $degreeToJob;
                $experience['qs_ranking'] = $qs_ranking;
                $experience['us_ranking'] = $us_ranking;
                $experience['top_qs_ranking'] = $top_qs_ranking;
                $experience['top_us_ranking'] = $top_us_ranking;
                $resume->educationalBackground()->create($experience);
            }
            // 创建项目经验和关联的技能
            foreach ($project_experience as $experience) {
                $project_start_date = $experience['project_start_date'];
                $project_end_date = $experience['project_end_date'];
                if (strtotime($project_end_date) <= strtotime($project_start_date)) {
                    throw new \Exception("项目结束日期必须大于项目开始日期");
                }
                $project = $resume->projectExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $project->skill()->createMany($experience['skill']);
                }
            }

            $full_time_experience = collect($full_time_experience)->sortBy('start_date');
            $mergedIntervals = new Collection();
            $full_time_experience->each(function ($experience) use (&$mergedIntervals, $resume) {
                $currentStart = Carbon::parse($experience['start_date']);
                $currentEnd = Carbon::parse($experience['end_date']);
                if ($currentStart->gt($currentEnd)) {
                    throw new \Exception('全职工作经验开始时间不能大于结束时间');
                }
                if ($mergedIntervals->isNotEmpty()) {
                    $lastInterval = $mergedIntervals->last();
                    $lastEnd = $lastInterval['end'];

                    // 如果当前时间段与最后一个合并的时间段重叠
                    if ($currentStart <= $lastEnd) {
                        // 合并时间段
                        $mergedIntervals->last()['end'] = max($lastEnd, $currentEnd);
                    } else {
                        // 添加新的时间段
                        $mergedIntervals->push(['start' => $currentStart, 'end' => $currentEnd]);
                    }
                } else {
                    // 第一个时间段
                    $mergedIntervals->push(['start' => $currentStart, 'end' => $currentEnd]);
                }
                $full_time = $resume->fullTimeExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $full_time->skill()->createMany($experience['skill']);
                }
            });

            // 计算合并后的时间段总年数
            $totalYears = $mergedIntervals->reduce(function ($carry, $interval) {
                return $carry + $interval['end']->diffInYears($interval['start'], true);
            }, 0);

            // 创建实习经验和关联的技能
            foreach ($internship_experience as $experience) {
                $start_date = $experience['start_date'];
                $end_date = $experience['end_date'];
                if (strtotime($end_date) <= strtotime($start_date)) {
                    throw new \Exception("实习结束日期必须大于实习开始日期");
                }
                $internship = $resume->internshipExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $internship->skill()->createMany($experience['skill']);
                }
            }
            $resume->skill()->createMany($skill);
            $resume->total_full_time_experience_years = round($totalYears, 1);
            $resume->top_degree = $top_degree->max();
            $resume->total_internship_experience_number = $resume->internshipExperience()->count();
            $resume->end_graduation_date = $resume->educationalBackground()->max('graduation_date');
            $resume->default = $resume->user->resume()->where('default', 1)->first() ? 0 : 1;
            $resume->save();
            DB::connection('plugin.admin.mysql')->commit();
        } catch (\Exception $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            Log::error('createResume');
            Log::error($e->getMessage());
            return $this->fail('失败');
        }
        return $this->success('成功');
    }

    function deleteResume(Request $request)
    {
        $resume_id = $request->post('resume_id');
        $row = Resume::find($resume_id);
        if (!$row) {
            return $this->fail('简历不存在');
        }
        if ($row->default == 1) {
            return $this->success('成功');
        }
        $row->delete();
        return $this->success('成功');
    }


    function editResume(Request $request)
    {
        try {
            #限流器 每个用户1秒内只能请求1次
            Limiter::check('user_' . $request->user_id, 1, 10);
        } catch (RateLimitException $e) {
            return $this->fail('请求频繁');
        }
        $resume_id = $request->post('resume_id');
        $name = $request->post('name');#简历名称
        $file = $request->post('file');#简历附件
        $educational_background = $request->post('educational_background');#教育背景
        $skill = $request->post('skill');#技术栈[{"name":"xxx"}]
        $project_experience = $request->post('project_experience');#项目背景
        $full_time_experience = $request->post('full_time_experience');#全职背景
        $internship_experience = $request->post('internship_experience');#实习背景
        if (empty($file)) {
            return $this->fail('请上传简历附件');
        }
        $resume = Resume::find($resume_id);
        if (!$resume) {
            return $this->fail('简历不存在');
        }
        $row = Resume::where(['user_id' => $request->user_id, 'name' => $name])->where('id', '<>', $resume_id)->first();
        if ($row) {
            return $this->fail('简历名称不能重复');
        }

        DB::connection('plugin.admin.mysql')->beginTransaction();
        try {
            $resume->educationalBackground()->delete();
            $resume->fullTimeExperience()->delete();
            $resume->fulltimeSkill()->delete();
            $resume->projectExperience()->delete();
            $resume->projectSkill()->delete();
            $resume->internshipExperience()->delete();
            $resume->internshipSkill()->delete();
            $resume->skill()->delete();
            // 映射岗位学历要求
            $degreeMapping = [
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 2,
                4 => 3,
                5 => 3,
                6 => 4,
                7 => 4,
            ];
            $top_degree = new Collection();
            foreach ($educational_background as $experience) {
                $cumulative_gpa = $experience['cumulative_gpa'] ?? 0;
                if ($cumulative_gpa > 4 || $cumulative_gpa < 0) {
                    throw new \Exception("总绩点必须在0-4之间");
                }
                $enrollment_date = $experience['enrollment_date'];
                $graduation_date = $experience['graduation_date'];
                if (strtotime($graduation_date) <= strtotime($enrollment_date)) {
                    throw new \Exception("毕业时间必须大于入学时间");
                }
                $university = University::find($experience['university_id']);
                if (!$university) {
                    throw new \Exception('学校不存在');
                }
                $qs_ranking = $university->qs_ranking;
                $us_ranking = $university->us_ranking;
                // 获取映射后的值，如果不存在则使用原始值
                $degreeToJob = $degreeMapping[$experience['degree']] ?? $experience['degree'];
                $top_degree->push($degreeToJob);
                if ($qs_ranking == 0) {
                    $top_qs_ranking = 0;
                } elseif ($qs_ranking <= 10) {
                    $top_qs_ranking = 1;
                } elseif ($qs_ranking <= 30) {
                    $top_qs_ranking = 2;
                } elseif ($qs_ranking <= 50) {
                    $top_qs_ranking = 3;
                } elseif ($qs_ranking <= 70) {
                    $top_qs_ranking = 4;
                } elseif ($qs_ranking <= 100) {
                    $top_qs_ranking = 5;
                } elseif ($qs_ranking <= 150) {
                    $top_qs_ranking = 6;
                } elseif ($qs_ranking <= 200) {
                    $top_qs_ranking = 7;
                } else {
                    $top_qs_ranking = 0;
                }
                if ($us_ranking == 0) {
                    $top_us_ranking = 0;
                } elseif ($us_ranking <= 10) {
                    $top_us_ranking = 1;
                } elseif ($us_ranking <= 30) {
                    $top_us_ranking = 2;
                } elseif ($us_ranking <= 50) {
                    $top_us_ranking = 3;
                } elseif ($us_ranking <= 70) {
                    $top_us_ranking = 4;
                } elseif ($us_ranking <= 100) {
                    $top_us_ranking = 5;
                } elseif ($us_ranking <= 150) {
                    $top_us_ranking = 6;
                } elseif ($us_ranking <= 200) {
                    $top_us_ranking = 7;
                } else {
                    $top_us_ranking = 0;
                }
                $experience['degree_to_job'] = $degreeToJob;
                $experience['qs_ranking'] = $qs_ranking;
                $experience['us_ranking'] = $us_ranking;
                $experience['top_qs_ranking'] = $top_qs_ranking;
                $experience['top_us_ranking'] = $top_us_ranking;
                $resume->educationalBackground()->create($experience);
            }
            // 创建项目经验和关联的技能
            foreach ($project_experience as $experience) {
                $project_start_date = $experience['project_start_date'];
                $project_end_date = $experience['project_end_date'];
                if (strtotime($project_end_date) <= strtotime($project_start_date)) {
                    throw new \Exception("项目结束日期必须大于项目开始日期");
                }
                $project = $resume->projectExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $project->skill()->createMany($experience['skill']);
                }
            }

            $full_time_experience = collect($full_time_experience)->sortBy('start_date');
            $mergedIntervals = new Collection();
            $full_time_experience->each(function ($experience) use (&$mergedIntervals, $resume) {
                $currentStart = Carbon::parse($experience['start_date']);
                $currentEnd = Carbon::parse($experience['end_date']);
                if ($currentStart->gt($currentEnd)) {
                    throw new \Exception('全职工作经验开始时间不能大于结束时间');
                }
                if ($mergedIntervals->isNotEmpty()) {
                    $lastInterval = $mergedIntervals->last();
                    $lastEnd = $lastInterval['end'];

                    // 如果当前时间段与最后一个合并的时间段重叠
                    if ($currentStart <= $lastEnd) {
                        // 合并时间段
                        $mergedIntervals->last()['end'] = max($lastEnd, $currentEnd);
                    } else {
                        // 添加新的时间段
                        $mergedIntervals->push(['start' => $currentStart, 'end' => $currentEnd]);
                    }
                } else {
                    // 第一个时间段
                    $mergedIntervals->push(['start' => $currentStart, 'end' => $currentEnd]);
                }
                $full_time = $resume->fullTimeExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $full_time->skill()->createMany($experience['skill']);
                }
            });

            // 计算合并后的时间段总年数
            $totalYears = $mergedIntervals->reduce(function ($carry, $interval) {
                return $carry + $interval['end']->diffInYears($interval['start'], true);
            }, 0);

            // 创建实习经验和关联的技能
            foreach ($internship_experience as $experience) {
                $start_date = $experience['start_date'];
                $end_date = $experience['end_date'];
                if (strtotime($end_date) <= strtotime($start_date)) {
                    throw new \Exception("实习结束日期必须大于实习开始日期");
                }
                $internship = $resume->internshipExperience()->create($experience);
                if (isset($experience['skill'])) {
                    $internship->skill()->createMany($experience['skill']);
                }
            }
            $resume->skill()->createMany($skill);


            $resume->name = $name;
            $resume->file = $file;
            $resume->total_full_time_experience_years = round($totalYears, 1);
            $resume->top_degree = $top_degree->max();
            $resume->total_internship_experience_number = $resume->internshipExperience()->count();
            $resume->end_graduation_date = $resume->educationalBackground()->max('graduation_date');
            $resume->save();
            DB::connection('plugin.admin.mysql')->commit();
        } catch (\Throwable $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            Log::error('editResume');
            Log::error($e->getMessage());
            return $this->fail('失败');
        }
        return $this->success('成功');
    }

    #联想搜索
    function indexSearch(Request $request)
    {
        $keyword = $request->post('keyword');
        try {
            // 查询公司和职位
            $companyQuery = Company::where('name', 'LIKE', [$keyword . '%'])->orderBy('name');
            $jobQuery = Job::where('position_name', 'LIKE', ['%' . $keyword . '%']);

            // 获取公司和职位的总数
            $companyCount = $companyQuery->count();
            $jobCount = $jobQuery->count();
            // 计算需要获取的公司和职位数量
            $totalNeeded = 10;
            if ($companyCount >= 5 && $jobCount >= 5) {
                $companyLimit = $totalNeeded - 5;
            } elseif ($companyCount >= 5 && $jobCount < 5) {
                $companyLimit = $totalNeeded - $jobCount;
            } else {
                $companyLimit = min($companyCount, $totalNeeded);
            }
            $jobLimit = min($jobCount, $totalNeeded - $companyLimit);

            // 执行查询并获取结果
            $company = $companyQuery->limit($companyLimit)->get()->map(function ($item) {
                return ['name' => $item->name];
            });
            $job = $jobQuery->limit($jobLimit)->get()->map(function ($item) {
                return ['name' => $item->position_name];
            });
            return $this->success('成功', ['company' => $company, 'job' => $job]);
        } catch (\Throwable $e) {
            return $this->fail('搜索失败，请稍后再试');
        }
    }


    #热门关键词
    function getHotKeyWord(Request $request)
    {
        $yesterday = Carbon::yesterday()->startOfDay();
        $endOfDay = Carbon::yesterday()->endOfDay();
        $topJobs = SendLog::with(['job'])->whereBetween('created_at', [$yesterday, $endOfDay])
            ->select('job_id', Db::raw('COUNT(*) as job_count'))
            ->groupBy('job_id')
            ->orderByDesc('job_count')
            ->limit(5)
            ->get();
        return $this->success('成功', $topJobs);
    }

    #订阅岗位
    function subscribeJob(Request $request)
    {
        $company_id = $request->post('company_id');
        $row = Company::find($company_id);
        if (!$row) {
            return $this->fail('公司不存在');
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->fail('用户不存在');
        }
        if (!$user->vip_status) {
            return $this->fail('请先开通VIP');
        }

        $count = Subscribe::where(['user_id' => $request->user_id])->count();
        if ($count >= 15) {
            return $this->fail('您已达到 VIP 订阅上限（15 家公司）。');
        }
        $subscribe = Subscribe::where(['user_id' => $request->user_id, 'company_name' => $row->name])->first();
        if ($subscribe) {
            return $this->fail('禁止重复订阅');
        }
        Subscribe::create([
            'user_id' => $request->user_id,
            'company_name' => $row->name,
        ]);
        return $this->success('成功');
    }

    #取消订阅
    function cancelSubscribeJob(Request $request)
    {
        $subscribe_id = $request->post('subscribe_id');
        if (!empty($subscribe_id)) {
            Subscribe::where(['id' => $subscribe_id])->delete();
        } else {
            Subscribe::where(['user_id' => $request->user_id])->delete();
        }
        return $this->success('成功');
    }

    #订阅列表
    function getSubscribeList(Request $request)
    {
        $rows = Subscribe::where(['user_id' => $request->user_id])->orderBy('id', 'desc')->get();
        return $this->success('成功', $rows);
    }


    #投递记录
    function getSendLogList(Request $request)
    {
        $rows = SendLog::withTrashed()->with(['resume','job','jobUser'])->where('resume_user_id', $request->user_id)->orderBy('id', 'desc')->paginate();
        return $this->success('成功', $rows);
    }


}

