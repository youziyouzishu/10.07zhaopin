<?php

namespace app\api\validate;


use Tinywan\Validate\Facade\Validate;


class Job extends Validate
{
    protected array $rule = [
        'position_name' => 'require',
        'position_description'=>'require',
        'position_type' => 'require',
        'work_mode'=>'require',
        'adult'=>'require',
        'sponsorship'=>'require',
    ];
}