<?php

session_start();

$captcha = new SimpleCaptcha();
$captcha->CreateImage();


class SimpleCaptcha {
	public $im;

	public $session_var   = 'captcha';

	public $width         = 300;
	public $height        = 100;

	public $minWordLength = 5;
	public $maxWordLength = 8;

	public $Yperiod       = 12;
	public $Yamplitude    = 14;
	public $Xperiod       = 11;
	public $Xamplitude    = 5;
	public $maxRotation   = 8;
	public $scale         = 2;

	public $backgroundColor = array(255, 255, 255);
	public $colors = array(
		array(27, 78, 181),// blue
		array(22, 163, 35),// green
		array(214, 36, 7) // red
	);

	public $resourcesPath = 'fonts/';

	public $fonts = array(
		array( 'spacing' => -3.0, 'minSize' => 27, 'maxSize' => 30, 'font' => 'AntykwaBold.ttf' ),
		array( 'spacing' => -1.5, 'minSize' => 28, 'maxSize' => 31, 'font' => 'Candice.ttf' ),
		array( 'spacing' => -2.0, 'minSize' => 24, 'maxSize' => 30, 'font' => 'Ding-DongDaddyO.ttf' ),
		array( 'spacing' => -2.0, 'minSize' => 30, 'maxSize' => 38, 'font' => 'Duality.ttf' ),
		array( 'spacing' => -2.0, 'minSize' => 24, 'maxSize' => 34, 'font' => 'Heineken.ttf' ),
		array( 'spacing' => -2.0, 'minSize' => 28, 'maxSize' => 32, 'font' => 'Jura.ttf' ),
		array( 'spacing' => -1.5, 'minSize' => 28, 'maxSize' => 32, 'font' => 'StayPuft.ttf' ),
		array( 'spacing' => -2.0, 'minSize' => 28, 'maxSize' => 34, 'font' => 'TimesNewRomanBold.ttf' ),
		array( 'spacing' => -1.0, 'minSize' => 20, 'maxSize' => 28, 'font' => 'VeraSansBold.ttf' ),
	);

	public function __construct() {}

	public function CreateImage() {
		$this->ImageAllocate();

		$text = $this->GetRandomCaptchaText();
		$this->WriteText($text);

		$_SESSION[$this->session_var] = $text;

		$this->WaveImage();

		imagefilter($this->im, IMG_FILTER_GAUSSIAN_BLUR);

		$this->ReduceImage();

		$this->WriteImage();
		$this->Cleanup();
	}

	protected function ImageAllocate() {
		if (!empty($this->im))
			imagedestroy($this->im);

		$width = $this->width * $this->scale;
		$height = $this->height * $this->scale;

		$this->im = imagecreatetruecolor($width, $height);

		$this->GdBgColor = imagecolorallocate(
			$this->im,
			$this->backgroundColor[0],
			$this->backgroundColor[1],
			$this->backgroundColor[2]
		);
		imagefilledrectangle(
			$this->im,
			0,
			0,
			$width,
			$height,
			$this->GdBgColor
		);

		$color = $this->colors[mt_rand(0, sizeof($this->colors) - 1)];
		$this->GdFgColor = imagecolorallocate(
			$this->im,
			$color[0],
			$color[1],
			$color[2]
		);

	}

	protected function GetRandomCaptchaText() {
		$length = rand($this->minWordLength, $this->maxWordLength);

		$words = 'abcdefghijlmnopqrstvwyz';
		$vocals = 'aeiou';

		$text = '';
		$vocal = rand(0, 1);

		for ($i = 0; $i < $length; $i++) {
			if ($vocal)
				$text .= $vocals[mt_rand(0,4)];
			else
				$text .= $words[mt_rand(0,22)];

			$vocal = !$vocal;
		}
		return $text;
	}

	protected function WriteText($text) {
		$fontcfg  = $this->fonts[mt_rand(0, sizeof($this->fonts) - 1)];
		$fontfile = $this->resourcesPath . $fontcfg['font'];

		$lettersMissing = $this->maxWordLength - strlen($text);
		$fontSizefactor = 1.5 + $lettersMissing * 0.09;

		$x = 20 * $this->scale;
		$y = round(($this->height * 27 / 40) * $this->scale);

		for ($i = 0, $length = strlen($text); $i < $length; $i++) {
			$degree = rand($this->maxRotation * -1, $this->maxRotation);
			$fontsize = rand($fontcfg['minSize'], $fontcfg['maxSize']) * $this->scale * $fontSizefactor;
			$letter = $text[$i];

			$coords = imagettftext(
				$this->im,
				$fontsize,
				$degree,
				$x,
				$y,
				$this->GdFgColor,
				$fontfile,
				$letter
			);

			$x = $coords[2] + $fontcfg['spacing'] * $this->scale;
		}
	}

	protected function WaveImage() {
		// X-axis wave generation
		$k = rand(0, 100);
		$xp = $this->scale * $this->Xperiod * rand(1, 3);
		$width = $this->width * $this->scale;
		$height = $this->height * $this->scale;

		for ($i = 0; $i < $width; $i++) {
			imagecopy(
				$this->im,
				$this->im,
				$i-1,
				sin($k + $i / $xp) * $this->scale * $this->Xamplitude,
				$i,
				0,
				1,
				$height
			);
		}

		// Y-axis wave generation
		$k = rand(0, 100);
		$yp = $this->scale * $this->Yperiod * rand(1, 2);

		for ($i = 0; $i < $height; $i++) {
			imagecopy(
				$this->im,
				$this->im,
				sin($k + $i / $yp) * $this->scale * $this->Yamplitude,
				$i-1,
				0,
				$i,
				$width,
				1
			);
		}
	}

	protected function ReduceImage() {
		$imResampled = imagecreatetruecolor($this->width, $this->height);
		imagecopyresampled(
			$imResampled,
			$this->im,
			0, 0, 0, 0,
			$this->width,
			$this->height,
			$this->width * $this->scale,
			$this->height * $this->scale
		);
		imagedestroy($this->im);
		$this->im = $imResampled;
	}

	protected function WriteImage() {
		header('Content-type: image/png');
		imagepng($this->im);
	}

	protected function Cleanup() {
		imagedestroy($this->im);
	}
}

