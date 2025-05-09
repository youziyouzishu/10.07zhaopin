<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Response;
use Webman\Route;




Route::fallback(function () {
    if (request()->header('accept') == 'application/json'){
        throw new \Tinywan\ExceptionHandler\Exception\RouteNotFoundException();
    }else{
        return new Response(404, [], file_get_contents(base_path('plugin' . DIRECTORY_SEPARATOR. 'admin' . DIRECTORY_SEPARATOR . 'public') . '/demos/error/404.html'));
    }
});


