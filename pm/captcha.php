<?php
/**
 * 验证码生成器（PHP GD）
 * 生成 4 位数字验证码，存入 session
 */
require_once __DIR__ . '/inc/config.php';

$code = '';
$chars = '0123456789';
for ($i = 0; $i < 4; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['pm_captcha'] = $code;

$width = 90;
$height = 38;

$img = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($img, 245, 247, 250);
imagefill($img, 0, 0, $bg);

// 干扰线
for ($i = 0; $i < 5; $i++) {
    $color = imagecolorallocate($img, mt_rand(150, 220), mt_rand(150, 220), mt_rand(150, 220));
    imageline($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $color);
}
// 干扰点
for ($i = 0; $i < 80; $i++) {
    $color = imagecolorallocate($img, mt_rand(120, 220), mt_rand(120, 220), mt_rand(120, 220));
    imagesetpixel($img, mt_rand(0, $width), mt_rand(0, $height), $color);
}

// 绘制数字
for ($i = 0; $i < 4; $i++) {
    $color = imagecolorallocate($img, mt_rand(20, 120), mt_rand(20, 120), mt_rand(20, 120));
    imagestring($img, 5, 12 + $i * 20, mt_rand(8, 14), $code[$i], $color);
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
imagepng($img);
imagedestroy($img);
