<?php

namespace app\api\controller;

use app\admin\model\Company;
use app\admin\model\Job;
use app\admin\model\Resume;
use app\admin\model\SendLog;
use app\admin\model\Subscribe;
use app\admin\model\University;
use app\admin\model\User;
use app\api\basic\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use support\Db;
use support\Request;
use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;

/**
 * 求职端
 */
class ResumeController extends Base
{

    protected $noNeedLogin = ['index'];

    #首页
    function index(Request $request)
    {
        $salary = $request->post('salary', ''); #薪资范围 0=50,000 以下 1=50000 - 80000 2=80000 - 120000 3=120000 - 160000 4=160000 - 200000 5=200000 以上
        $eligible = $request->post('eligible', '');#是否认证HR 0否 1是
        $province = $request->post('province', '');#所属州
        $position_type = $request->post('position_type', '');#工作类型
        $work_mode = $request->post('work_mode', '');#工作模式:0=In-Person=现场办公,1=Hybrid=混合办公,2=Remote=远程办公
        $keyword = $request->post('keyword', '');
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
        $query = Job::where(['status' => 1])
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
                $query
                    ->orWhere('position_name', 'like', '%' . $keyword . '%')
                    ->orWhereHas('user', function (Builder $query) use ($keyword) {
                        $query->where('company_name', 'like', '%' . $keyword . '%');
                    });
            })
            //认证HR筛选
            ->when(!empty($eligible) || $eligible == 0, function (Builder $query) {
                $query
                    ->whereHas('user', function (Builder $query) {
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
        if (!empty($user)) {
            $user_profile = $user->profile;
        } else {
            $user_profile = null;
        }
        $defaultResume = Resume::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认简历
        if (!empty($request->user_id) && !empty($defaultResume) && !empty($user_profile)) {
            //如果用户登录
            $fulltimeSkill = $defaultResume->fulltimeSkill->pluck('name')->toArray(); #全职技能
            $internshipSkill = $defaultResume->internshipSkill->pluck('name')->toArray();#实习技能
            $projectSkill = $defaultResume->projectSkill->pluck('name')->toArray();#项目技能
            $skill = $defaultResume->skill->pluck('name')->toArray();#技术栈
            $top_secret = $user_profile->top_secret;#绝密权限
            $adult = $user_profile->adult;#是否成人
            $sponsorship = $user_profile->sponsorship;#是否签证支持
            $from_limitation = $user_profile->from_limitation;#受限国家
            $us_citizen = $user_profile->us_citizen;#美国公民
            $query
                //绝密权限
                ->when(function ($query) {
                    return $query->where('top_secret', 1);
                }, function (Builder $query) use ($top_secret) {
                    return $query->where('top_secret', $top_secret);
                })
                //是否成人
                ->when(function ($query) {
                    return $query->where('adult', 1);
                }, function (Builder $query) use ($adult) {
                    return $query->where('adult', $adult);
                })
                //是否签证支持
                ->when(!empty($user_profile->sponsorship), function (Builder $query) use ($sponsorship) {
                    return $query->where('sponsorship', $sponsorship);
                })
                //受限国家
                ->when(function ($query) {
                    return $query->where('from_limitation', 0);
                }, function (Builder $query) use ($from_limitation) {
                    return $query->where('from_limitation', $from_limitation);
                })
                //是否美国公民
                ->when(function ($query) {
                    return $query->where('us_citizen', 1);
                }, function (Builder $query) use ($us_citizen) {
                    return $query->where('us_citizen', $us_citizen);
                })
                //技术栈筛选
                ->when(function (Builder $query) {
                    return $query->whereHas('skill');
                }, function (Builder $query) use ($skill) {
                    $query->whereHas('skill', function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    }, '<=', count($skill));
                })
                //学历筛选
                ->when(function (Builder $query) use ($defaultResume) {
                    // 首先检查候选人的教育背景是否符合岗位的最低学历要求
                    $degreeRequirements = $query->value('degree_requirements');
                    return in_array($degreeRequirements, $defaultResume->educationalBackground->pluck('degree_to_job')->toArray());
                }, function (Builder $query) use ($defaultResume) {
                    // 符合
                    // 提前获取岗位要求的值
                    $degreeRequirements = $query->value('degree_requirements');
                    $overallGpaRequirement = $query->value('overall_gpa_requirement');
                    $majorGpaRequirement = $query->value('major_gpa_requirement');
                    $degreeQsRanking = $query->value('degree_qs_ranking');
                    $degreeUsRanking = $query->value('degree_us_ranking');

                    // 筛选出符合的教育背景
                    $filteredEducationalBackground = $defaultResume->educationalBackground->filter(function ($item) use ($degreeRequirements, $overallGpaRequirement, $majorGpaRequirement, $degreeQsRanking, $degreeUsRanking, $query) {
                        $qsCondition = ($degreeQsRanking == 0) || ($item->top_qs_ranking <= $degreeQsRanking && $item->top_qs_ranking != 0);
                        $usCondition = ($degreeUsRanking == 0) || ($item->top_us_ranking <= $degreeUsRanking && $item->top_us_ranking != 0);
                        return $item->degree === $degreeRequirements &&
                            $item->cumulative_gpa >= $overallGpaRequirement &&
                            $query->whereHas('major', function (Builder $query) use ($item) {
                                $query->where('name', $item->major);
                            }) &&
                            $item->major_gpa >= $majorGpaRequirement &&
                            $qsCondition &&
                            $usCondition;
                    });

                    if ($filteredEducationalBackground->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    }
                }, function (Builder $query) use ($defaultResume) {
                    $degreeRequirements = $query->value('degree_requirements');
                    // 不符合
                    $top = $defaultResume->educationalBackground->pluck('degree_to_job')->max();
                    if ($top > $degreeRequirements) {
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
                    $query->whereHas('skill', function (Builder $query) use ($projectSkill) {
                        $query->whereIn('name', $projectSkill);
                    }, '<=', count($projectSkill));
                })
                //实习技术栈匹配
                ->when(function (Builder $query) {
                    return $query->value('internship_tech_stack_match') == 1;
                }, function (Builder $query) use ($internshipSkill) {
                    $query->whereHas('skill', function (Builder $query) use ($internshipSkill) {
                        $query->whereIn('name', $internshipSkill);
                    }, '<=', count($internshipSkill));
                })
                //全职技术栈匹配
                ->when(function (Builder $query) {
                    return $query->value('full_time_tech_stack_match') == 1;
                }, function (Builder $query) use ($fulltimeSkill) {
                    $query->whereHas('skill', function (Builder $query) use ($fulltimeSkill) {
                        $query->whereIn('name', $fulltimeSkill);
                    }, '<=', count($fulltimeSkill));
                })
                //全职工作最低年限要求
                ->when(function (Builder $query) {
                    return $query->value('minimum_full_time_internship_experience_years') > 0;
                }, function (Builder $query) use ($defaultResume) {
                    $query->where('minimum_full_time_internship_experience_years', '<=', $defaultResume->total_full_time_experience_years);
                })
                //实习工作最低段数要求
                ->when(function (Builder $query) {
                    return $query->value('minimun_internship_experience_number') > 0;
                }, function (Builder $query) use ($defaultResume) {
                    $query->where('minimun_internship_experience_number', '<=', $defaultResume->total_internship_experience_number);
                })
                //应届生毕业日期
                ->when(function (Builder $query) {
                    return $query->value('graduation_date') != null;
                }, function (Builder $query) use ($defaultResume) {
                    $query->where('graduation_date', $defaultResume->end_graduation_date);
                })
                //是否允许已申请用户重复申请:0=false,1=true
                ->when(function (Builder $query) {
                    return $query->value('allow_duplicate_application') == 0;
                }, function (Builder $query) use ($defaultResume) {
                    $query->whereDoesntHave('sendLog', function (Builder $query) use ($defaultResume) {
                        $query->where('resume_id', $defaultResume->id);
                    });
                })
                //在线状态排序
                ->with(['user' => function (Builder $builder) {
                    $builder->orderByDesc('online');
                }])
                //非必备技能排序
                ->when(function (Builder $query) {
                    return $query->whereHas('niceSkill');
                }, function (Builder $query) use ($skill) {
                    $query->withCount(['niceSkill' => function (Builder $query) use ($skill) {
                        $query->whereIn('name', $skill);
                    }])
                        ->orderByDesc('nice_skill_count');
                })
                ->orderByDesc('updated_at');
        }
        $rows = $query->paginate()->items();
        return $this->success('成功', ['list' => $rows, 'company' => $company]);
    }

    #详情
    function detail(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::find($job_id);
        return $this->success('成功', $row);
    }

    #投递简历
    function send(Request $request)
    {
        $job_id = $request->post('job_id');
        $defaultResume = Resume::where(['user_id' => $request->user_id, 'default' => 1])->first();
        if (!$defaultResume) {
            return $this->fail('请先选择默认简历');
        }
        SendLog::create([
            'resume_id' => $defaultResume->id,
            'job_id' => $job_id
        ]);
        return $this->success();
    }


    #获取简历列表
    function getResumeList(Request $request)
    {
        $rows = Resume::with(['skill'])->where(['user_id' => $request->user_id])->get();
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
        Resume::where(['user_id' => $request->user_id])->update(['default' => 0]);
        $row->default = 1;
        $row->save();
        return $this->success();
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
        $name = $request->post('name');#简历名称
        $file = $request->post('file');#简历附件
        $educational_background = $request->post('educational_background');#教育背景
        $project_experience = $request->post('project_experience');#项目背景
        $full_time_experience = $request->post('full_time_experience');#全职背景
        $internship_experience = $request->post('internship_experience');#实习背景
        $skill = $request->post('skill');#技术栈[{"name":"xxx"}]
        DB::connection('plugin.admin.mysql')->beginTransaction();
        try {
            $resume = Resume::create([
                'user_id' => $request->user_id,
                'name' => $name,
                'file' => $file,
            ]);
            // 映射岗位学历要求
            $degreeMapping = [
                2 => 2,
                3 => 2,
                4 => 3,
                5 => 3,
                6 => 4,
                7 => 4,
            ];
            $top_degree = new Collection();
            foreach ($educational_background as $experience) {
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
            $resume->save();
            DB::connection('plugin.admin.mysql')->commit();
        } catch (\Throwable $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            return $this->fail($e->getMessage());
        }
        return $this->success();
    }


    function editResume(Request $request)
    {
        $resume_id = $request->post('resume_id');
        $name = $request->post('name');#简历名称
        $file = $request->post('file');#简历附件
        $educational_background = $request->post('educational_background');#教育背景
        $skill = $request->post('skill');#技术栈[{"name":"xxx"}]
        $project_experience = $request->post('project_experience');#项目背景
        $full_time_experience = $request->post('full_time_experience');#全职背景
        $internship_experience = $request->post('internship_experience');#实习背景
        $resume = Resume::find($resume_id);

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
                2 => 2,
                3 => 2,
                4 => 3,
                5 => 3,
                6 => 4,
                7 => 4,
            ];
            $top_degree = new Collection();
            foreach ($educational_background as $experience) {
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
            return $this->fail($e->getMessage());
        }
        return $this->success();
    }

    #联想搜索
    function indexSearch(Request $request)
    {
        $keyword = $request->post('keyword');
        try {
            // 查询公司和职位
            $companyQuery = Company::where('name', 'like', '%' . $keyword . '%');
            $jobQuery = Job::where('position_name', 'like', '%' . $keyword . '%');

            // 获取公司和职位的总数
            $companyCount = $companyQuery->count();
            $jobCount = $jobQuery->count();

            // 计算需要获取的公司和职位数量
            $totalNeeded = 10;
            $companyLimit = min($companyCount, $totalNeeded);
            $jobLimit = min($jobCount, $totalNeeded - $companyLimit);

            // 执行查询并获取结果
            $company = $companyQuery->limit($companyLimit)->get();
            $job = $jobQuery->limit($jobLimit)->get();
            return $this->success('成功', ['company' => $company, 'job' => $job]);
        } catch (\Throwable $e) {
            return $this->fail('搜索失败，请稍后再试');
        }
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
        //todo
//        if ($user->vip_status == 0) {
//            return $this->fail('请先开通VIP');
//        }

        $count = Subscribe::where(['user_id' => $request->user_id])->count();
        if ($count >= 19) {
            return $this->fail('最多订阅20个');
        }
        $subscribe = Subscribe::where(['user_id' => $request->user_id, 'company_name' => $row->name])->first();
        if ($subscribe) {
            return $this->fail('禁止重复订阅');
        }
        Subscribe::create([
            'user_id' => $request->user_id,
            'company_name' => $row->name,
        ]);
        return $this->success();
    }

    #取消订阅
    function cancelSubscribeJob(Request $request)
    {
        $subscribe_id = $request->post('subscribe_id');
        if (!empty($subscribe_id)){
            Subscribe::where(['id'=>$subscribe_id])->delete();
        }else{
            Subscribe::where(['user_id' => $request->user_id])->delete();
        }
        return $this->success();
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
        $resumeList = Resume::where(['user_id' => $request->user_id])->get();
        $rows = SendLog::withTrashed()->whereIn('resume_id', $resumeList->pluck('id'))->orderBy('id', 'desc')->paginate()->items();
        return $this->success('成功', $rows);
    }


}
