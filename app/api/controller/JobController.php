<?php

namespace app\api\controller;

use app\admin\model\EducationalBackground;
use app\admin\model\HrRelation;
use app\admin\model\Job;
use app\admin\model\Major;
use app\admin\model\Resume;
use app\admin\model\SendLog;
use app\admin\model\User;
use app\admin\model\UsersHr;
use app\api\basic\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use plugin\admin\app\common\Util;
use plugin\smsbao\api\Smsbao;
use support\Cache;
use support\Db;
use support\Log;
use support\Request;
use Webman\RateLimiter\Limiter;
use Webman\RateLimiter\RateLimitException;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Redis;

/**
 *HR端
 */
class JobController extends Base
{

    function index(Request $request)
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
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        $user = User::find($request->user_id);
        $defaultJobSkill = $defaultJob->skill->pluck('name')->toArray();

        $defaultJobNiceSkill = $defaultJob->niceSkill->pluck('name')->toArray();

        $query = Resume::where(['default' => 1])
            ->with(['skill'])
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
            ->when($defaultJob->top_secret == 0, function (Builder $query) use ($defaultJob) {
                $query->whereHas('user', function (Builder $query) use ($defaultJob) {
                    $query->whereHas('profile', function (Builder $query) {
                        $query->where('top_secret', 0);
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
            //先按照用户的在线状态排序
            ->with(['user' => function ($builder) {
                $builder->orderByDesc('online');
            }])
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

    function getQSRanking(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        $data = [
            1 => 'Top 10',
            2 => 'Top 30',
            3 => 'Top 50',
            4 => 'Top 70',
            5 => 'Top 100',
            6 => 'Top 150',
            7 => 'Top 200'
        ];
        if ($degree == $defaultJob->degree_requirements) {
            $degree_qs_ranking = $defaultJob->degree_qs_ranking;
            if ($degree_qs_ranking) {
                $data = array_filter($data, function ($value, $key) use ($degree_qs_ranking) {
                    return $key < $degree_qs_ranking;
                }, ARRAY_FILTER_USE_BOTH);
            }
        }
        return $this->success('成功', $data);
    }

    function getUSRanking(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        $data = [
            1 => 'Top 10',
            2 => 'Top 30',
            3 => 'Top 50',
            4 => 'Top 70',
            5 => 'Top 100',
            6 => 'Top 150',
            7 => 'Top 200'
        ];
        if ($degree == $defaultJob->degree_requirements) {
            $degree_us_ranking = $defaultJob->degree_us_ranking;
            if ($degree_us_ranking) {
                $data = array_filter($data, function ($value, $key) use ($degree_us_ranking) {
                    return $key < $degree_us_ranking;
                }, ARRAY_FILTER_USE_BOTH);
            }
        }
        return $this->success('成功', $data);
    }

    function getCumulativeGpa(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }

        if ($degree == $defaultJob->degree_requirements) {
            $min = $defaultJob->overall_gpa_requirement;
        } else {
            $min = 0;
        }
        return $this->success('成功', ['min' => $min]);
    }

    function getMajorGpa(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        if ($degree == $defaultJob->degree_requirements) {
            $min = $defaultJob->major_gpa_requirement;
        } else {
            $min = 0;
        }
        return $this->success('成功', ['min' => $min]);
    }

    function getMajor(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }

        if ($degree == $defaultJob->degree_requirements) {
            $major = $defaultJob->major;
        } else {
            $major = Major::all();
        }
        return $this->success('成功', $major);
    }

    function getFullTimeInternshipExperienceYears(Request $request)
    {
        $degree = $request->post('degree');#2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        if ($degree == $defaultJob->degree_requirements) {
            $min = $defaultJob->minimum_full_time_internship_experience_years;
        } else {
            $min = 0;
        }
        return $this->success('成功', ['min' => $min]);
    }


    #创建岗位
    function createJob(Request $request)
    {
        try {
            #限流器 每个用户1秒内只能请求1次
            Limiter::check('user_' . $request->user_id, 1, 1);
        } catch (RateLimitException $e) {
            return $this->fail('请求频繁');
        }
        $position_name = $request->post('position_name');
        $position_description = $request->post('position_description');
        $minimum_salary = $request->post('minimum_salary');
        $maximum_salary = $request->post('maximum_salary');
        $position_type = $request->post('position_type');
        $adult = $request->post('adult'); #是否只招聘成年人:0=false=否,1=true=是
        $work_mode = $request->post('work_mode');
        $sponsorship = $request->post('sponsorship');
        $project_tech_stack_match = $request->post('project_tech_stack_match');
        $internship_tech_stack_match = $request->post('internship_tech_stack_match');
        $full_time_tech_stack_match = $request->post('full_time_tech_stack_match');
        $degree_requirements = $request->post('degree_requirements');
        $degree_qs_ranking = $request->post('degree_qs_ranking');
        $degree_us_ranking = $request->post('degree_us_ranking');
        $overall_gpa_requirement = $request->post('overall_gpa_requirement');
        $major_gpa_requirement = $request->post('major_gpa_requirement');
        $minimum_full_time_internship_experience_years = $request->post('minimum_full_time_internship_experience_years');
        $minimum_internship_experience_number = $request->post('minimum_internship_experience_number');
        $top_secret = $request->post('top_secret');
        $graduation_date = $request->post('graduation_date');
        $position_location = $request->post('position_location');
        $expected_number_of_candidates = $request->post('expected_number_of_candidates');
        $from_limitation = $request->post('from_limitation');
        $us_citizen = $request->post('us_citizen');
        $major = $request->post('major');# 数组 [{"name":"xx"},{"name":"xx"}]
        $skill = $request->post('skill'); # 数组 [{"name":"xx"},{"name":"xx"}]
        $nice_skill = $request->post('nice_skill'); # 数组 [{"name":"xx"},{"name":"xx"}]
        if (!empty($minimum_salary) || !empty($maximum_salary)) {
            if ($minimum_salary > $maximum_salary) {
                return $this->fail('薪资范围错误');
            }
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->fail('用户不存在');
        }

        $job_count = $user->job()->count();
        if ($user->vip_status) {
            if ($job_count >= 15) {
                return $this->fail('vip用户最多只能创建15个岗位');
            }

        } else {
            if ($job_count >= 3) {
                return $this->fail('非vip用户最多只能创建3个岗位');
            }
            if (!empty($degree_qs_ranking) || !empty($degree_us_ranking)) {
                return $this->fail('非vip用户不能设置qs_ranking和us_ranking');
            }
        }


        if (empty($user->company_name) || empty($user->position)) {
            return $this->fail('请先完善所属公司和岗位');
        }
        DB::connection('plugin.admin.mysql')->beginTransaction();
        try {
            $data = [
                'user_id' => $request->user_id,
                'position_name' => $position_name,
                'position_description' => $position_description,
                'minimum_salary' => empty($minimum_salary) ? 0 : $minimum_salary,
                'maximum_salary' => empty($maximum_salary) ? 0 : $maximum_salary,
                'position_type' => $position_type,
                'adult' => $adult,
                'work_mode' => $work_mode,
                'sponsorship' => $sponsorship,
                'project_tech_stack_match' => empty($project_tech_stack_match) ? 0 : $project_tech_stack_match,
                'internship_tech_stack_match' => empty($internship_tech_stack_match) ? 0 : $internship_tech_stack_match,
                'full_time_tech_stack_match' => empty($full_time_tech_stack_match) ? 0 : $full_time_tech_stack_match,
                'degree_requirements' => $degree_requirements,
                'degree_qs_ranking' => empty($degree_qs_ranking) ? 0 : $degree_qs_ranking,
                'degree_us_ranking' => empty($degree_us_ranking) ? 0 : $degree_us_ranking,
                'overall_gpa_requirement' => empty($overall_gpa_requirement) ? 0 : $overall_gpa_requirement,
                'major_gpa_requirement' => empty($major_gpa_requirement) ? 0 : $major_gpa_requirement,
                'minimum_full_time_internship_experience_years' => empty($minimum_full_time_internship_experience_years) ? 0 : $minimum_full_time_internship_experience_years,
                'minimum_internship_experience_number' => empty($minimum_internship_experience_number) ? 0 : $minimum_internship_experience_number,
                'top_secret' => $top_secret,
                'graduation_date' => empty($graduation_date) ? null : $graduation_date,
                'position_location' => $position_location,
                'expected_number_of_candidates' => empty($expected_number_of_candidates) ? null : $expected_number_of_candidates,
                'from_limitation' => $from_limitation,
                'us_citizen' => $us_citizen,
            ];
            $job = Job::create($data);
            $job->major()->createMany($major);
            $job->skill()->createMany($skill);
            $job->niceSkill()->createMany($nice_skill);
            DB::connection('plugin.admin.mysql')->commit();
        } catch (\Throwable $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            Log::error($e->getMessage());
            return $this->fail('失败');
        }
        return $this->success('成功');
    }


    #下架
    function removal(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::find($job_id);
        if (!$row || $row->status != 1) {
            return $this->fail('岗位不存在');
        }
        $row->status = 0;
        $row->save();
        return $this->success('成功');
    }

    function setDefaultJob(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::find($job_id);
        if (empty($row)) {
            return $this->fail('岗位不存在');
        }
        Job::where(['user_id' => $request->user_id])->where('id', '<>', $job_id)->update(['default' => 0]);
        $row->default = 1;
        $row->save();
        return $this->success('成功');
    }

    function getDefaultJob(Request $request)
    {
        $row = Job::with(['user'])->where(['user_id' => $request->user_id, 'default' => 1])->first();
        if (empty($row)) {
            return $this->fail('岗位不存在');
        }
        return $this->success('成功', $row);
    }

    #更新上架
    function publish(Request $request)
    {
        try {
            #限流器 每个用户1秒内只能请求1次
            Limiter::check('user_' . $request->user_id, 1, 1);
        } catch (RateLimitException $e) {
            return $this->fail('请求频繁');
        }
        $job_id = $request->post('job_id');
        $position_name = $request->post('position_name');
        $position_description = $request->post('position_description');
        $minimum_salary = $request->post('minimum_salary');
        $maximum_salary = $request->post('maximum_salary');
        $position_type = $request->post('position_type');
        $adult = $request->post('adult'); #是否只招聘成年人:0=false=否,1=true=是
        $work_mode = $request->post('work_mode');
        $sponsorship = $request->post('sponsorship');
        $project_tech_stack_match = $request->post('project_tech_stack_match');
        $internship_tech_stack_match = $request->post('internship_tech_stack_match');
        $full_time_tech_stack_match = $request->post('full_time_tech_stack_match');
        $degree_requirements = $request->post('degree_requirements');
        $degree_qs_ranking = $request->post('degree_qs_ranking');
        $degree_us_ranking = $request->post('degree_us_ranking');
        $overall_gpa_requirement = $request->post('overall_gpa_requirement');
        $major_gpa_requirement = $request->post('major_gpa_requirement');
        $minimum_full_time_internship_experience_years = $request->post('minimum_full_time_internship_experience_years');
        $minimum_internship_experience_number = $request->post('minimum_internship_experience_number');
        $top_secret = $request->post('top_secret');
        $graduation_date = $request->post('graduation_date');
        $position_location = $request->post('position_location');
        $expected_number_of_candidates = $request->post('expected_number_of_candidates');
        $from_limitation = $request->post('from_limitation');
        $us_citizen = $request->post('us_citizen');
        $allow_duplicate_application = $request->post('allow_duplicate_application');
        $major = $request->post('major');# 数组 [{"name":"xx"},{"name":"xx"}]
        $skill = $request->post('skill'); # 数组 [{"name":"xx"},{"name":"xx"}]
        $nice_skill = $request->post('nice_skill'); # 数组 [{"name":"xx"},{"name":"xx"}]
        if (!empty($minimum_salary) || !empty($maximum_salary)) {
            if ($minimum_salary > $maximum_salary) {
                return $this->fail('薪资范围错误');
            }
        }
        $row = Job::find($job_id);
        if (!$row || $row->status != 0) {
            return $this->fail('岗位不存在');
        }

        $user = User::find($request->user_id);
        if (!$user->vip_status) {
            if (!empty($degree_qs_ranking) || !empty($degree_us_ranking)) {
                return $this->fail('非vip用户不能设置qs_ranking和us_ranking');
            }
        }


        DB::connection('plugin.admin.mysql')->beginTransaction();
        try {
            $row->major()->delete();
            $row->skill()->delete();
            $row->niceSkill()->delete();
            $row->position_name = $position_name;
            $row->position_description = $position_description;
            $row->minimum_salary = empty($minimum_salary) ? 0 : $minimum_salary;
            $row->maximum_salary = empty($maximum_salary) ? 0 : $maximum_salary;
            $row->position_type = $position_type;
            $row->adult = $adult;
            $row->work_mode = $work_mode;
            $row->sponsorship = $sponsorship;
            $row->project_tech_stack_match = $project_tech_stack_match;
            $row->internship_tech_stack_match = $internship_tech_stack_match;
            $row->full_time_tech_stack_match = $full_time_tech_stack_match;
            $row->degree_requirements = $degree_requirements;
            $row->degree_qs_ranking = empty($degree_qs_ranking) ? 0 : $degree_qs_ranking;
            $row->degree_us_ranking = empty($degree_us_ranking) ? 0 : $degree_us_ranking;;
            $row->overall_gpa_requirement = $overall_gpa_requirement;
            $row->major_gpa_requirement = empty($major_gpa_requirement) ? 0 : $major_gpa_requirement;
            $row->minimum_full_time_internship_experience_years = $minimum_full_time_internship_experience_years;
            $row->minimum_internship_experience_number = $minimum_internship_experience_number;
            $row->top_secret = $top_secret;
            $row->graduation_date = $graduation_date;
            $row->position_location = $position_location;
            $row->expected_number_of_candidates = empty($expected_number_of_candidates) ? null : $expected_number_of_candidates;
            $row->from_limitation = $from_limitation;
            $row->us_citizen = $us_citizen;
            $row->allow_duplicate_application = $allow_duplicate_application;
            $row->expire_time = empty($row->user->vip_expire_at) || $row->user->vip_expire_at->isPast() ? Carbon::now()->addDays(7)->toDateTimeString() : Carbon::now()->addDays(14)->toDateTimeString();
            $row->status = 1;
            $row->save();
            $row->major()->createMany($major);
            $row->skill()->createMany($skill);
            $row->niceSkill()->createMany($nice_skill);
            if ($row->allow_duplicate_application == 1) {
                $row->sendLog->each(function (SendLog $log) {
                    $log->delete();
                });

            }
            $queue = Redis::send('job', ['event' => 'job_expire', 'job_id' => $row->id], $row->expire_time->timestamp - time());
            if (!$queue) {
                throw new \Exception('加入队列失败');
            }
            DB::connection('plugin.admin.mysql')->commit();
        } catch (\Throwable $e) {
            DB::connection('plugin.admin.mysql')->rollBack();
            Log::error($e->getMessage());
            return $this->fail('失败');
        }

        return $this->success('成功');
    }

    function getJobList(Request $request)
    {
        $status = $request->post('status');#状态:0=Removal=下架,1=Publish=上架
        $rows = Job::where(['user_id' => $request->user_id])
            ->when(!empty($status) || $status === 0, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->withCount(['sendLog' => function ($query) {
                $query->whereRaw('wa_send_log.created_at > wa_job.updated_at');
            }])
            ->get();
        return $this->success('成功', $rows);
    }

    function getJobDetail(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::with(['skill', 'niceSkill', 'major'])->find($job_id);
        return $this->success('成功', $row);
    }

    #邀请HR
    function addHr(Request $request)
    {
        $mobile = $request->post('mobile');
        $email = $request->post('email');
        $row = User::where(['mobile' => $mobile, 'email' => $email, 'type' => 1])->first();
        if (!$row) {
            return $this->fail('HR不存在');
        }
        if ($row->hr_type != 1) {
            return $this->fail('该用户已被认证');
        }
        $user = User::find($request->user_id);
        if (!$user) {
            return $this->fail('用户不存在');
        }
        if ($user->hr_type != 3) {
            return $this->fail('您还不是超级HR');
        }
        $has = UsersHr::where(['user_id' => $request->user_id, 'to_user_id' => $row->id])->first();
        if ($has) {
            return $this->fail('您已经邀请过该HR');
        }
        $hr_count = UsersHr::where(['user_id' => $request->user_id])->count();
        if ($hr_count >= 10) {
            return $this->fail('邀请数量已达上限');
        }
        $invite = Util::generateOrdersn();
        Cache::set('invite_' . $invite, ['user_id' => $user->id, 'to_user_id' => $row->id], 60 * 60 * 24);

        $url = '<a href="' . 'https://1007zhaopin.62.hzgqapp.com/api/notify/beHr?invite=' . $invite . '">' . 'https://1007zhaopin.62.hzgqapp.com/api/notify/beHr?invite=' . $invite . '</a>';
        #发送短信
        $content = "$user->company_name $user->position $user->last_name $user->name invites you to become a certified HR. Click the link to complete certification: $url";
        $account = Smsbao::getSmsbaoAccount();
        if (!$account) {
            return $this->fail('未配置发信账户');
        }
        $sendUrl = Smsbao::SMSBAO_URL . "wsms?sms&u=" . $account['Username'] . "&p=" . $account['Password'] . "&m=" . urlencode('+1' . $mobile) . "&c=" . urlencode($content);
        Client::send('job', ['event' => 'sms_add_hr', 'url' => $sendUrl]);
        #发送邮箱
        Client::send('job', ['event' => 'email_add_hr', 'email' => $email, 'template' => 'invite', 'company_name' => $user->company_name, 'position' => $user->position, 'last_name' => $user->last_name, 'name' => $user->name, 'url' => $url]);

        return $this->success('成功');
    }


    function deleteJob(Request $request)
    {
        $job_id = $request->post('job_id');
        $row = Job::find($job_id);
        if (!$row) {
            return $this->fail('岗位不存在');
        }
        $row->delete();
        return $this->success('成功');
    }

    #获取邀请HR列表
    function getHrList(Request $request)
    {
        $rows = UsersHr::where(['user_id' => $request->user_id])
            ->with('toUser')
            ->get();
        return $this->success('成功', $rows);
    }

    #删除HR
    function deleteHr(Request $request)
    {
        $hr_id = $request->post('hr_id');
        $hr = UsersHr::find($hr_id);
        if (!$hr) {
            return $this->fail('HR不存在');
        }
        $hr->user->hr_type = 1;#变为普通HR
        $hr->user->save();
        $hr->delete();
        #发信给自己 (超级HR)
        Client::send('job', ['event' => 'email_delete_hr_1', 'email' => $hr->user->email]);
        #发信给认证HR
        Client::send('job', ['event' => 'email_delete_hr_2', 'email' => $hr->toUser->email]);
        return $this->success('成功');
    }

    #打招呼
    function relation(Request $request)
    {
        $to_user_id = $request->post('to_user_id');
        $resume_id = $request->post('resume_id');
        $user = User::find($to_user_id);
        if (!$user->vip_status) {
            $count = HrRelation::where(['user_id' => $request->user_id, 'to_user_id' => $to_user_id])->whereDate('updated_at', Carbon::today())->count();
            if ($count >= 5) {
                return $this->fail('您今天已经申请过5次');
            }
        }
        $resume = Resume::find($resume_id);
        $defaultJob = Job::where(['user_id' => $request->user_id, 'default' => 1])->first();#默认岗位
        if (!$defaultJob) {
            return $this->fail('请先设置默认岗位');
        }
        if (!$resume) {
            return $this->fail('简历不存在');
        }
        $row = SendLog::where(['job_id' => $defaultJob->id, 'resume_id' => $resume->id])->first();
        if ($row) {
            $row->updated_at = date('Y-m-d H:i:s');
            $row->save();
        } else {
            SendLog::create([
                'resume_id' => $resume->id,
                'resume_user_id' => $resume->user_id,
                'job_id' => $defaultJob->id,
                'job_user_id' => $defaultJob->user_id,
            ]);
        }
        $row = HrRelation::where(['user_id' => $request->user_id, 'to_user_id' => $to_user_id])->first();
        if ($row) {
            $row->updated_at = date('Y-m-d H:i:s');
            $row->save();
        } else {
            HrRelation::create([
                'user_id' => $request->user_id,
                'to_user_id' => $to_user_id,
            ]);
        }
        return $this->success('成功');
    }


}
