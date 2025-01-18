<?php

namespace app\api\controller;

use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\Resume;
use app\admin\model\User;
use app\api\basic\Base;
use support\Request;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];


    public function index(Request $request)
    {
        $job_id = $request->post('job_id',16);
        $resume_id = $request->post('resume_id',36);
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
        $jobSkills = $job->skill->pluck('name');
        $resumeSkills = $resume->skill->pluck('name');
        // 判断 job_skills 中的所有技能是否全部在 resume_skills 中
        $allSkillsMatch = $jobSkills->every(function ($skill) use ($resumeSkills) {
            return $resumeSkills->contains($skill);
        });
        if (!$allSkillsMatch) {
            return $this->fail('1');
        }
        #学历匹配
        $degreeRequirements = $job->degree_requirements;

        if (in_array($job->degree_requirements, $resume->educationalBackground->pluck('degree_to_job')->toArray())) {
            $overallGpaRequirement = $job->overall_gpa_requirement;
            $majorGpaRequirement = $job->major_gpa_requirement;
            $degreeQsRanking = $job->degree_qs_ranking;
            $degreeUsRanking = $job->degree_us_ranking;
            // 筛选出符合的教育背景
            $filteredEducationalBackground = $resume->educationalBackground->filter(function (EducationalBackground $item) use ($degreeRequirements, $overallGpaRequirement, $majorGpaRequirement, $degreeQsRanking, $degreeUsRanking, $job) {
                $qsCondition = ($degreeQsRanking == 0) || ($item->top_qs_ranking <= $degreeQsRanking && $item->top_qs_ranking != 0);
                $usCondition = ($degreeUsRanking == 0) || ($item->top_us_ranking <= $degreeUsRanking && $item->top_us_ranking != 0);
                return $item->degree_to_job == $degreeRequirements &&
                    $item->cumulative_gpa >= $overallGpaRequirement &&
                    in_array( $item->major,$job->major->pluck('name')->toArray()) &&
                    $item->major_gpa >= $majorGpaRequirement &&
                    $qsCondition &&
                    $usCondition;
            });
            if ($filteredEducationalBackground->isEmpty()) {
                return $this->fail('2');
            }
        } else {
            // 不符合
            if ($resume->top_degree > $degreeRequirements) {
                $overallGpaRequirement = $job->overall_gpa_requirement;
                $majorGpaRequirement = $job->major_gpa_requirement;
                $degreeQsRanking = $job->degree_qs_ranking;
                $degreeUsRanking = $job->degree_us_ranking;
                if ($overallGpaRequirement != 0 || $majorGpaRequirement != 0 || $degreeQsRanking != 0 || $degreeUsRanking != 0) {
                    return $this->fail('3');
                }
            } else {
                return $this->fail('4');
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
                return $this->fail('5');
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
                return $this->fail('6');
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
                return $this->fail('7');
            }
        }

        //全职工作最低年限要求
        if ($resume->total_full_time_experience_years < $job->minimum_full_time_internship_experience_years) {
            return $this->fail('8');
        }

        //实习工作最低段数要求
        if ($resume->total_internship_experience_number < $job->minimum_internship_experience_number) {
            return $this->fail('9');
        }

        //应届生毕业日期
        if (!empty($job->graduation_date) && $resume->end_graduation_date != $job->graduation_date) {
            return $this->fail('10');
        }

        //是否允许已申请用户重复申请
        if ($job->allow_duplicate_application == 0 && $resume->sendLog->where('job_id', $job_id)->count() > 0) {
            return $this->fail('11');
        }
        return $this->success('恭喜你，你符合岗位要求');

    }


}
