<?php
return [
    'enable' => true,
    'exception' => [
        // 是否记录异常到日志
        'enable' => true,
        // 不会记录到日志的异常类
        'dontReport' => [
            support\exception\BusinessException::class,
            Tinywan\ExceptionHandler\Exception\BadRequestHttpException::class,
            Tinywan\ExceptionHandler\Exception\UnauthorizedHttpException::class,
            Tinywan\ExceptionHandler\Exception\ForbiddenHttpException::class,
            Tinywan\ExceptionHandler\Exception\NotFoundHttpException::class,
            Tinywan\ExceptionHandler\Exception\RouteNotFoundException::class,
            Tinywan\ExceptionHandler\Exception\TooManyRequestsHttpException::class,
            Tinywan\ExceptionHandler\Exception\ServerErrorHttpException::class,
            Tinywan\Validate\Exception\ValidateException::class,
            Tinywan\Jwt\Exception\JwtTokenException::class
        ]
    ],
    'dontReport' => [
        'app' => [
        ],
        'controller' => [
        ],
        'action' => [
        ],
        'path' => [
        ]
    ]
];
