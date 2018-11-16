<?php
namespace ePHP\Http;

/**
 * 图片缩略图、验证码
 *
 * 验证码使用示例
 * 在view上添加图片：<img src="/image/captcha" onclick="this.src='/img/captcha?v='+ Date.now()" />
 *
 * // 校验验证码
 * echo Image::checkCaptcha() ? '验证码正确' : '验证码错误';
 *
 * // 生成验证码
 * public funciton captcha()
 * {
 *     Image::captcha();
 * }
 *
 * // 获取图片的信息
 * print_r(Image::getInfo('/home/a.jpg'));
 *
 * // 使用gd生成缩略图
 * // 生成 a.jpg 的缩略图,宽度:100 高度:120
 * Image::thumbImg('a.jpg', 'a-thumb.jpg', 100, 120);
 *
 * </code>
 */

class Image
{
    /**
     * 得到图片的信息
     *
     * @param $imgfile 文件文件名
     * @return mixed
     */
    public static function getInfo($imgfile)
    {
        $imageInfo = getimagesize($imgfile);
        if ($imageInfo !== false) {
            $imageType = strtolower(substr(image_type_to_extension($imageInfo[2]), 1));
            $imageSize = filesize($imgfile);
            $info      = array(
                "width"  => $imageInfo[0],
                "height" => $imageInfo[1],
                "type"   => $imageType,
                "size"   => $imageSize,
                "mime"   => $imageInfo['mime'],
            );
            return $info;
        } else {
            return false;
        }
    }

    /**
     * 生成缩略图
     *
     * 缩略图会根据源图的比例进行缩略的，生成的缩略图格式是JPG
     *
     * @param string $srcfile 源文件名
     * @param string $dstfile 生成缩略图的文件名
     * @param string $thumbWidth 缩略图最大宽度
     * @param string $thumbHeight 缩略图最大高度
     */
    public static function thumbImg($srcfile, $dstfile, $thumbWidth, $thumbHeight)
    {
        $imageinfo = getimagesize($srcfile);
        if (empty($imageinfo)) {
            throw_error('只支持gif,jpg,png的图片');
        }

        // dd($imageinfo);

        if ($imageinfo[2] == 1) {
            $im = imagecreatefromgif($srcfile);
        } elseif ($imageinfo[2] == 2) {
            $im = imagecreatefromjpeg($srcfile);
        } elseif ($imageinfo[2] == 3) {
            $im = imagecreatefrompng($srcfile);
        } else {
            throw_error('只支持gif,jpg,png的图片');
        }

        $w = $imageinfo[0];
        $h = $imageinfo[1];

        if ($thumbWidth / $thumbHeight > $w / $h) {
            $nh = $thumbHeight;
            $nw = ($w * $thumbHeight) / $h;
        } else {
            $nw = $thumbWidth;
            $nh = ($h * $thumbWidth) / $w;
        }
        // echo "w: $nw , h: $nh";exit;

        $ni = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($ni, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($ni, $dstfile);
        imagedestroy($im);

        return file_exists($dstfile);
    }

    /**
     * 验证码
     *
     * 图片尺寸,50x25
     *
     * @param int $length 验证码长度
     * @param int $mode 0大小写字母，1数字，2大写字母，3小写字母,5大小写+数字
     * @param string $type 图片类型
     * @param bool $hasborder 图片边框有否
     * @return null
     */
    public static function captcha($length = 4, $mode = 3, $type = 'png', $hasborder = true)
    {
        $randval                   = \ePHP\Misc\Func::randString($length, $mode);
        $_SESSION['imgVerifyCode'] = md5(strtolower($randval));

        $width  = 50;
        $height = 25;

        $width = ($length * 9 + 10) > $width ? $length * 9 + 10 : $width;
        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $im = @imagecreatetruecolor($width, $height);
        } else {
            $im = @imagecreate($width, $height);
        }

        // 背景色
        $backColor = imagecolorallocate($im, 252, 252, 252);

        if ($hasborder) {
            $border_color = 238;
        } else {
            $border_color = 255;
        }

        $borderColor = imagecolorallocate($im, $border_color, $border_color, $border_color); //边框色
        $pointColor  = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)); //点颜色

        @imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $backColor);
        @imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderColor);
        $stringColor = imagecolorallocate($im, mt_rand(0, 200), mt_rand(0, 120), mt_rand(0, 120));

        // 干扰
        for ($i = 0; $i < 10; $i++) {
            $fontcolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagearc($im, mt_rand(-10, $width), mt_rand(-10, $height), mt_rand(30, 300), mt_rand(20, 200), 55, 44, $fontcolor);
        }

        for ($i = 0; $i < 25; $i++) {
            $fontcolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $pointColor);
        }

        @imagestring($im, 5, 5, 3, $randval, $stringColor);

        header("Content-type: image/" . $type);
        $imageFun = 'Image' . $type;
        $imageFun($im);
        imagedestroy($im);
    }

    /**
     * 检测输入的验证码是否正确
     *
     * @param $verifyCode 用户输入的验证码
     * @return bool
     */
    public static function checkCaptcha($verifyCode)
    {
        if (empty($_SESSION['imgVerifyCode'])) {
            return false;
        }

        return $_SESSION['imgVerifyCode'] == md5(strtolower($verifyCode));
    }
}
