<?php

namespace app\api\controller;

use app\admin\model\Company;
use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\JobMajor;
use app\admin\model\JobNiceSkill;
use app\admin\model\JobSkill;
use app\admin\model\Resume;
use app\admin\model\Subscribe;
use app\admin\model\User;
use app\api\basic\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use support\Log;
use support\Request;
use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];

    function index()
    {
        $a = ['aaa'=>1];
        dump($a);
    }
    
    function test()
    {
        

    }

    public function hr(Request $request)
    {
        $request->user_id = 7;
        $online = $request->post('online', '');#候选人在线状态:0=否,1=是
        $send_status = $request->post('send_status', '');#投递状态 0=未投递,1=已投递,
        $skill = $request->post('skill');#岗位所需技术 array ["xxxx","xxxxx"]
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $top_qs_ranking = $request->post('top_qs_ranking'); #QS排名 接口获取
        $top_us_ranking = $request->post('top_us_ranking'); #US排名 接口获取
        $overall_gpa = $request->post('overall_gpa');#总绩点要求 接口获取
        $major = $request->post('major');#专业要求 接口获取
        $major_gpa = $request->post('major_gpa');#专业绩点 接口获取
        $minimum_full_time_internship_experience_years = $request->post('minimum_full_time_internship_experience_years');#全职工作年限 接口获取
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        $defaultJobSkill = $defaultJob->skill->pluck('name')->toArray();

        $defaultJobNiceSkill = $defaultJob->niceSkill->pluck('name')->toArray();

        $query = Resume::where(['default' => 1])
            ->whereHas('user', function (Builder $query) {
                $query->where('show_status', 1);
            })
            ->when($defaultJob->allow_duplicate_application == 0, function (Builder $query) use ($defaultJob) {
                $query->whereDoesntHave('sendLog', function (Builder $query) use ($defaultJob) {
                    $query->where('job_id', $defaultJob->id);
                });
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
            ->when($defaultJob->top_secret == 0, function (Builder $query) use ($defaultJob) {
                $query->whereHas('user', function (Builder $query) use ($defaultJob) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('top_secret', 0);
                    });
                });
            })
            //技术栈要求筛选
            ->when(!empty($defaultJobSkill), function (Builder $query) use ($defaultJobSkill) {

                $query->whereHas('skill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //项目技术栈要求筛选
            ->when($defaultJob->project_tech_stack_match == 1, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('projectSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //实习技术栈要求筛选
            ->when($defaultJob->internship_tech_stack_match == 1, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('internshipSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //全职技术栈要求筛选
            ->when($defaultJob->full_time_tech_stack_match == 1, function (Builder $query) use ($defaultJobSkill) {
                $query->whereHas('fulltimeSkill', function (Builder $query) use ($defaultJobSkill) {
                    $query->whereIn('name', $defaultJobSkill);
                }, '>=', count($defaultJobSkill));
            })
            //学历筛选
            ->when(function (Builder $query) use ($defaultJob) {
                return EducationalBackground::where('id',$query->value('id'))->where('degree_to_job',$defaultJob->degree_requirements)->exists();
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
            //投递状态筛选
            ->when(!empty($send_status) || $send_status == 0, function (Builder $query) use ($send_status, $defaultJob) {
                //未投递
                if ($send_status == 0) {
                    $query->whereDoesntHave('sendLog', function (Builder $query) use ($defaultJob) {
                        $query->where('job_id', $defaultJob->id);
                    });
                }
                //已投递
                if ($send_status == 1) {
                    $query->whereHas('sendLog', function (Builder $query) use ($defaultJob) {
                        $query->where('job_id', $defaultJob->id);
                    });
                }
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
            ->with(['user' => function ($builder) {
                $builder->orderByDesc('online');
            }])
            ->when(!empty($defaultJobNiceSkill), function (Builder $query) use ($defaultJobNiceSkill) {
                $query->withCount(['skill' => function (Builder $query) use ($defaultJobNiceSkill) {
                    $query->whereIn('name', $defaultJobNiceSkill);
                }])
                    ->orderByDesc('skill_count');
            })
            ->orderByDesc('updated_at');
        $rows = $query->paginate();
        return $this->success('成功', $rows);
    }

    function resume(Request $request)
    {
        $salary = $request->post('salary', ''); #薪资范围 0=50,000 以下 1=50000 - 80000 2=80000 - 120000 3=120000 - 160000 4=160000 - 200000 5=200000 以上
        $eligible = $request->post('eligible', '');#是否认证HR 0否 1是
        $province = $request->post('province', '');#所属州
        $position_type = $request->post('position_type', '');#工作类型
        $work_mode = $request->post('work_mode', '');#工作模式:0=In-Person=现场办公,1=Hybrid=混合办公,2=Remote=远程办公
        $keyword = $request->post('keyword', '');#关键词
        $resume_id = 36;
        try {
            $request->user_id = JwtToken::getCurrentId();
        } catch (JwtTokenException $e) {
            $request->user_id = 0;
        }
        $request->user_id = 3;
        $company = Company::where('name', $keyword)->first();
        if (!empty($company)) {
            $subscribeStatus = Subscribe::where(['user_id' => $request->user_id, 'company_name' => $company->name])->first();
            $company->setAttribute('is_subscribe', $subscribeStatus ? 1 : 0);
        }

        $query = Job::where(['status' => 1])
            ->with(['user' => function ($query) {
                //在线状态排序
                $query->orderByDesc('online');
            }])
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
                $query->where('position_name', $keyword)->orWhereHas('user', function (Builder $query) use ($keyword) {
                    $query->where('company_name', $keyword);
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
        $user = User::find($request->user_id); #个人信息
        if (!empty($user) && !empty($user->profile)) {
            $top_secret = $user->profile->top_secret;#绝密权限
            $adult = $user->profile->adult;#是否成人
            $sponsorship = $user->profile->sponsorship;#是否签证支持
            $from_limitation = $user->profile->from_limitation;#受限国家
            $us_citizen = $user->profile->us_citizen;#美国公民
            $query = $query
                //绝密权限
                ->when(function (Builder $query) {
                    return $query->value('top_secret') == 1;
                }, function (Builder $query) use ($top_secret) {
                    return $query->where('top_secret', $top_secret);
                })
                //是否成人
                ->when(function (Builder $query) {
                    return $query->value('adult') == 1;
                }, function (Builder $query) use ($adult) {
                    return $query->where('adult', $adult);
                })
                //是否签证支持
                ->when($sponsorship == 1, function (Builder $query) use ($sponsorship) {
                    return $query->where('sponsorship', $sponsorship);
                })
                //受限国家
                ->when(function (Builder $query) {
                    return $query->value('from_limitation') == 1;
                }, function (Builder $query) use ($from_limitation) {
                    return $query->where('from_limitation', $from_limitation);
                })
                //是否美国公民
                ->when(function (Builder $query) {
                    return $query->value('us_citizen') == 1;
                }, function (Builder $query) use ($us_citizen) {
                    return $query->where('us_citizen', $us_citizen);
                });
        }

        #指定简历
        $resume = Resume::where(['user_id' => $request->user_id, 'id' => $resume_id])->first();#默认简历
        if (!empty($resume)) {
            //如果有指定简历
            $fulltimeSkill = $resume->fulltimeSkill->pluck('name')->toArray(); #全职技能
            $internshipSkill = $resume->internshipSkill->pluck('name')->toArray();#实习技能
            $projectSkill = $resume->projectSkill->pluck('name')->toArray();#项目技能
            $skill = $resume->skill->pluck('name')->toArray();#技术栈
            $query = $query
                //技术栈筛选
                ->when(function (Builder $query) {
                    return JobSkill::where('job_id', $query->value('id'))->exists();
                }, function (Builder $query) use ($skill) {
                    $query->whereDoesntHave('skill', function ($query) use ($skill) {
                        $query->whereNotIn('name', $skill);
                    });
                })
                //学历筛选
                ->when(function (Builder $query) use ($resume) {
                    // 首先检查候选人的教育背景是否符合岗位的最低学历要求
                    if (empty($resume->educationalBackground)) {
                        return false;
                    }
                    $degreeRequirements = $query->value('degree_requirements');
                    $educationalDegrees = $resume->educationalBackground->pluck('degree_to_job')->toArray();
                    return in_array($degreeRequirements, $educationalDegrees);
                }, function (Builder $query) use ($resume) {
                    // 符合
                    // 提前获取岗位要求的值
                    $degreeRequirements = $query->value('degree_requirements');
                    $overallGpaRequirement = $query->value('overall_gpa_requirement');
                    $majorGpaRequirement = $query->value('major_gpa_requirement');
                    $degreeQsRanking = $query->value('degree_qs_ranking');
                    $degreeUsRanking = $query->value('degree_us_ranking');
                    // 检查是否存在匹配的 major
                    // 检查是否存在匹配的 major
                    $majorExists = JobMajor::where('id', $query->value('id'))->exists();
                    // 筛选出符合的教育背景
                    $filteredEducationalBackground = $resume->educationalBackground->filter(function (EducationalBackground $item) use ($degreeRequirements, $overallGpaRequirement, $majorGpaRequirement, $degreeQsRanking, $degreeUsRanking, $majorExists, $query) {
                        $qsCondition = ($degreeQsRanking == 0) || ($item->top_qs_ranking <= $degreeQsRanking && $item->top_qs_ranking != 0);
                        $usCondition = ($degreeUsRanking == 0) || ($item->top_us_ranking <= $degreeUsRanking && $item->top_us_ranking != 0);
                        if ($majorExists) {
                            $majorCondition = $query->whereHas('major', function (Builder $query) use ($item) {
                                $query->where('name', $item->major);
                            });
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
                        $query->whereRaw('1 = 0');
                    }

                }, function (Builder $query) use ($resume) {
                    $degreeRequirements = $query->value('degree_requirements');
                    // 不符合
                    if ($resume->top_degree > $degreeRequirements) {
                        $overallGpaRequirement = $query->value('overall_gpa_requirement');
                        $majorGpaRequirement = $query->value('major_gpa_requirement');
                        $degreeQsRanking = $query->value('degree_qs_ranking');
                        $degreeUsRanking = $query->value('degree_us_ranking');
                        if ($overallGpaRequirement != 0 || $majorGpaRequirement != 0 || $degreeQsRanking != 0 || $degreeUsRanking != 0) {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
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
                //应届生毕业日期
                ->when(function (Builder $query) {
                    return $query->value('graduation_date') != null;
                }, function (Builder $query) use ($resume) {
                    $query->where('graduation_date', $resume->end_graduation_date);
                })
                //是否允许已申请用户重复申请:0=false,1=true
                ->when(function (Builder $query) {
                    return $query->value('allow_duplicate_application') == 0;
                }, function (Builder $query) use ($request) {
                    $query->whereDoesntHave('sendLog', function (Builder $query) use ($request) {
                        $query->where('resume_user_id', $request->user_id);
                    });
                })
                //非必备技能排序
                ->when(function (Builder $query) {
                    return JobNiceSkill::where('job_id', $query->value('id'))->exists();
                }, function (Builder $query) use ($skill) {
                    $query->withCount(['niceSkill' => function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    }])
                        ->orderByDesc('nice_skill_count');
                });

        }

        $query = $query
            //简历更新时间排序
            ->orderByDesc('updated_at');
        $rows = $query->paginate();

        return $this->success('成功', ['list' => $rows, 'company' => $company]);
    }

    function send(Request $request)
    {
        $job_id = 20;
        $resume_id = 36;
        $request->user_id = 3;
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
        dump($jobSkills);
        $resumeSkills = $resume->skill->pluck('name');#简历的技术栈
        dump($resumeSkills);
        // 判断 job_skills 中的所有技能是否全部在 resume_skills 中
        $allSkillsMatch = $jobSkills->every(function ($skill) use ($resumeSkills) {
            return $resumeSkills->contains($skill);
        });
        dump($allSkillsMatch);
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
        if ($job->allow_duplicate_application == 0 && $resume->sendLog()->where('job_id',$job->id)->count() > 0) {
            return $this->fail('岗位要求可能已经更新，你的背景不符合岗位要求11');
        }

        return $this->success('成功');
    }


}
