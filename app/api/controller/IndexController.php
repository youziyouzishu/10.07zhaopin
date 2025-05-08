<?php

namespace app\api\controller;

use app\admin\model\Company;
use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\JobNiceSkill;
use app\admin\model\Resume;
use app\admin\model\Subscribe;
use app\admin\model\User;
use app\api\basic\Base;
use Illuminate\Database\Eloquent\Builder;
use support\Db;
use support\Request;
use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;
use Workerman\Timer;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];

    function index(Request $request)
    {
        // 初始金额
        $initialAmount = 100;

        // 复利利率
        $interestRate = 0.1;

        // 天数
        $days = 4;

        // 计算复利后的金额
        $finalAmount = $initialAmount * pow((1 + $interestRate), $days);

        // 输出结果
        dump( number_format($finalAmount, 2) . ' 元');
    }

    function resume(Request $request)
    {
        $salary = $request->post('salary', ''); #薪资范围 0=50,000 以下 1=50000 - 80000 2=80000 - 120000 3=120000 - 160000 4=160000 - 200000 5=200000 以上
        $eligible = $request->post('eligible', '');#是否认证HR 0否 1是
        $province = $request->post('province', '');#所属州
        $position_type = $request->post('position_type', '');#工作类型
        $work_mode = $request->post('work_mode', '');#工作模式:0=In-Person=现场办公,1=Hybrid=混合办公,2=Remote=远程办公
        $keyword =$request->post('keyword', '');#关键词

        try {
            $request->user_id = JwtToken::getCurrentId();
        } catch (JwtTokenException $e) {
            $request->user_id = 0;
        }

        $resume_id = 54;
        $request->user_id  = 43;

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
                                $sub->where('wa_job.overall_gpa_requirement', '>', 0)
                                    ->orWhere('wa_job.major_gpa_requirement', '>', 0)
                                    ->orWhere('wa_job.degree_qs_ranking', '>', 0)
                                    ->orWhere('wa_job.degree_us_ranking', '>', 0);
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

    public function job(Request $request)
    {
        $online = $request->post('online', '');#候选人在线状态:0=否,1=是
        $skill = $request->post('skill');#岗位所需技术 array ["xxxx","xxxxx"]
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $top_qs_ranking = $request->post('top_qs_ranking'); #QS排名 接口获取
        $top_us_ranking = $request->post('top_us_ranking'); #US排名 接口获取
        $overall_gpa = $request->post('overall_gpa');#总绩点要求 接口获取
        $major = $request->post('major');#专业要求 接口获取
        $major_gpa = $request->post('major_gpa');#专业绩点 接口获取
        $minimum_full_time_internship_experience_years = $request->post('minimum_full_time_internship_experience_years');#全职工作年限 接口获取
        $request->user_id = 46;
        $defaultJob = Job::find(51);#默认岗位
        dump($defaultJob->id);
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        $user = User::find($request->user_id);
        $defaultJobSkill = $defaultJob->skill->pluck('name')->toArray();

        $defaultJobNiceSkill = $defaultJob->niceSkill->pluck('name')->toArray();

        $query = Resume::where(['default' => 1])
            //先按照用户的在线状态排序
            ->with(['user'])
            ->with(['skill'])
            ->orderBy(
                User::select('online')
                    ->whereColumn('id', 'wa_resume.user_id'),
                'desc'
            )
            ->whereHas('user', function (Builder $query) {
                $query->where('show_status', 1);
            })
            //投递记录筛选
            ->whereDoesntHave('sendLog', function (Builder $query) use ($defaultJob) {
                $query->where('job_id', $defaultJob->id);
            })
            // 签证担保筛选
            ->when($defaultJob->sponsorship == 0, function (Builder $query) {
                $query->whereHas('user', function (Builder $query) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('sponsorship', 0);
                    });
                });
            })
            //成人筛选
            ->when($defaultJob->adult == 1, function (Builder $query) {
                $query->whereHas('user', function (Builder $query) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('adult', 1);
                    });
                });
            })
            //国家限制筛选
            ->when($defaultJob->from_limitation == 0, function (Builder $query) {
                $query->whereHas('user', function (Builder $query) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('from_limitation', 0);
                    });
                });
            })
            //是否美国公民筛选
            ->when($defaultJob->us_citizen == 1, function (Builder $query) {
                $query->whereHas('user', function (Builder $query) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('us_citizen', 1);
                    });
                });
            })
            //绝密权限
            ->when($defaultJob->top_secret == 1, function (Builder $query) use ($defaultJob) {
                $query->whereHas('user', function (Builder $query) use ($defaultJob) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('top_secret', 1);
                    });
                });
            })
            //技术栈要求筛选
            ->when(!empty($defaultJobSkill) && $user->vip_status, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('skill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //项目技术栈要求筛选
            ->when($defaultJob->project_tech_stack_match == 1 && $user->vip_status, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('projectSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //实习技术栈要求筛选
            ->when($defaultJob->internship_tech_stack_match == 1 && $user->vip_status, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('internshipSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //全职技术栈要求筛选
            ->when($defaultJob->full_time_tech_stack_match == 1 && $user->vip_status, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('fulltimeSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //学历筛选
            ->when(function (Builder $query) use ($defaultJob) {
                return EducationalBackground::where('resume_id', $query->value('id'))->where('degree_to_job', $defaultJob->degree_requirements)->exists();
            }, function (Builder $query) use ($defaultJob) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($defaultJob) {
                    $query
                        ->where('degree_to_job', $defaultJob->degree_requirements)//筛选出符合学历的教育背景
                        ->where('cumulative_gpa', '>=', $defaultJob->overall_gpa_requirement)//筛选出符合总绩点的教育背景
                        ->when(!empty($defaultJob->major->pluck('name')->toArray()), function ($query, $defaultJob) {
                            $query->whereIn('major', $defaultJob->major->pluck('name')->toArray());//筛选出符合专业要求的教育背景
                        })
                        ->where('major_gpa', '>=', $defaultJob->major_gpa_requirement)//筛选出符合专业绩点的教育背景
                        ->when(!empty($defaultJob->degree_qs_ranking), function (Builder $query) use ($defaultJob) { //筛选出符合QS排名的教育背景
                            $query->where('top_qs_ranking', '<>', 0)->where('top_qs_ranking', '<=', $defaultJob->degree_qs_ranking);
                        })
                        ->when(!empty($defaultJob->degree_us_ranking), function (Builder $query) use ($defaultJob) { //筛选出符合US排名的教育背景
                            $query->where('top_us_ranking', '<>', 0)->where('top_us_ranking', '<=', $defaultJob->degree_us_ranking);
                        });
                });
            }, function (Builder $query) use ($defaultJob) {
                $overallGpaRequirement = $defaultJob->overall_gpa_requirement;
                $majorGpaRequirement = $defaultJob->major_gpa_requirement;
                $degreeQsRanking = $defaultJob->degree_qs_ranking;
                $degreeUsRanking = $defaultJob->degree_us_ranking;
                if ($overallGpaRequirement == 0 && $majorGpaRequirement == 0 && $degreeQsRanking == 0 && $degreeUsRanking == 0) {
                    if ($query->value('top_degree') < $defaultJob->degree_requirements) {
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            //全职工作最低年限要求
            ->when($defaultJob->minimum_full_time_internship_experience_years > 0, function (Builder $query) use ($defaultJob) {
                $query->where('total_full_time_experience_years', '>=', $defaultJob->minimum_full_time_internship_experience_years);
            })
            //实习段数
            ->when($defaultJob->minimum_internship_experience_number > 0, function (Builder $query) use ($defaultJob) {
                $query->where('total_internship_experience_number', '<=', $defaultJob->minimum_internship_experience_number);
            })
            //应届生毕业日期
            ->when($defaultJob->graduation_date != null, function (Builder $query) use ($defaultJob) {
                $query->where('end_graduation_date', $defaultJob->graduation_date);
            })
            //在线状态筛选
            ->when(!empty($online) || $online == 0, function (Builder $query) use ($online) {
                $query->whereHas('user', function (Builder $query) use ($online) {
                    $query->where('online', $online);
                });
            })
            //岗位所需技术筛选
            ->when(!empty($skill), function (Builder $query) use ($skill) {
                $query->where(function (Builder $query) use ($skill) {
                    $query->orwhereHas('projectSkill', function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    })->orWhereHas('internshipSkill', function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    })->orWhereHas('fulltimeSkill', function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    });
                });
            })
            //手动学历筛选
            ->when(!empty($degree), function (Builder $query) use ($degree) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($degree) {
                    $query->where('degree_to_job', $degree);
                });
            })
            //手动QS排名
            ->when(!empty($top_qs_ranking), function (Builder $query) use ($top_qs_ranking) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($top_qs_ranking) {
                    $query->where('top_qs_ranking', '<>', 0)->where('top_qs_ranking', '<=', $top_qs_ranking);
                });
            })
            //手动US排名
            ->when(!empty($top_us_ranking), function (Builder $query) use ($top_us_ranking) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($top_us_ranking) {
                    $query->where('top_us_ranking', '<>', 0)->where('top_us_ranking', '<=', $top_us_ranking);
                });
            })
            //手动总绩点筛选
            ->when(!empty($overall_gpa), function (Builder $query) use ($overall_gpa) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($overall_gpa) {
                    $query->where('cumulative_gpa', '>=', $overall_gpa);
                });
            })
            //手动总绩点筛选
            ->when(!empty($overall_gpa), function (Builder $query) use ($overall_gpa) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($overall_gpa) {
                    $query->where('cumulative_gpa', '>=', $overall_gpa);
                });
            })
            //手动专业筛选
            ->when(!empty($major), function (Builder $query) use ($major) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($major) {
                    $query->whereIn('major', $major);
                });
            })
            //手动专业绩点筛选
            ->when(!empty($major_gpa), function (Builder $query) use ($major_gpa) {
                $query->whereHas('educationalBackground', function (Builder $query) use ($major_gpa) {
                    $query->where('major_gpa', '>=', $major_gpa);
                });
            })
            //手动筛选全职工作年限
            ->when(!empty($minimum_full_time_internship_experience_years), function (Builder $query) use ($minimum_full_time_internship_experience_years) {
                $query->where('total_full_time_experience_years', '>=', $minimum_full_time_internship_experience_years);
            })

            //按照岗位所需技术排序
            ->when(!empty($defaultJobNiceSkill), function (Builder $query) use ($defaultJobNiceSkill) {
                $query->withCount(['skill' => function (Builder $query) use ($defaultJobNiceSkill) {
                    $query->whereIn('name', $defaultJobNiceSkill);
                }])
                    ->orderByDesc('skill_count');
            })
            //按照发布时间排序
            ->orderByDesc('updated_at');
        $rows = $query->paginate();
        return $this->success('成功', $rows);
    }

    function send(Request $request)
    {

    }


}
