form-php
========

スタイリッシュなシンタックスが特徴のフォームバリデーションライブラリです。


特徴
----

- チェーンメソッドを使った簡単なシンタックス
- バリデーションのルールとフォームの HTML のロジックが分離されている
- バリデーション用のフィルターを拡張できる
- フォームの HTML をルールを元に自動生成
- Ajax 対応
- 自動返信メール対応
- nonce 機能 (CSRF対策)
- CAPTCHA 対応 (スパム対策)


Quick Start
-----------

ファイルの一番最初でバリデーションのルールを設定します。

```php
<?php

$cf = new Form(array(
	'prefix' => 'cf',
	'mail'   => true,
	'nonce'  => 'gYxfXm30Q7hruJKB',
));

/*	Validation Rules
-----------------------------------------------*/
$cf->add('name', true)->minlen(3)->maxlen(12);

$cf->add('email', true)->type('email');

$cf->add('birthdate')->filter('datetime', 'Y/m/d');

$cf->add('gender')->set_option(array(
	'Male',
	'Female',
));

$cf->add('color+')->set_option(array(
	'Red',
	'Orange',
	'Yellow',
	'Green',
	'Blue',
	'Indigo',
	'Violet',
))->maxselect(3);

$cf->add('message')->maxlen(1000);

/*	Send Email
-----------------------------------------------*/
$cf->submit(array(
	'from' => 'Contact <contact@example.com>',
	'bcc' => 'contact@example.com',
	'to' => 'email',
	'subject' => 'Contact',
	'body' => '
----------------------------------------
Timestamp: {{DATE}}
----------------------------------------
Name: {{name}}
----------------------------------------
Email: {{email}}
----------------------------------------
Birthdate: {{birthdate}}
----------------------------------------
Gender: {{gender}}
----------------------------------------
Colors:
{{color+}}
----------------------------------------
Message:
{{message}}
----------------------------------------
',
));

?>

<form id="cf" action="form.php" method="post">
	<h3>Your name (required)</h3>
	<div><?php $cf->html->text('name'); ?></div>

	<h3>Email (required)</h3>
	<div><?php $cf->html->text('email'); ?></div>

	<h3>Birthdate</h3>
	<div><?php $cf->html->text('date'); ?></div>

	<h3>Gender</h3>
	<ul><?php $cf->html->option('gender'); ?></ul>

	<h3>Colors you like (select 3 at most)</h3>
	<ul><?php $cf->html->option('color+'); ?></ul>

	<h3>Message</h3>
	<ul><?php $cf->html->textarea('message'); ?></ul>

	<?php $cf->html->nonce(); ?>

	<button type="submit">Send</button>
</form>

<script>
	$('#cf').ajaxForm({
		dataType : 'json',
		data : {
			_ajax_call : 1
		},
		success : function (data, status, xhr, $form) {
			console.log(data);

			if (data.processed == true) {
				// successed

				return true;
			} else {
				// failed

				return false;
			}
		}
	});
</script>
```


初期化
------

```php
$cf = new Form($config);
```

``Form`` に連想配列を渡して設定をします。

<table>
	<tr>
		<th>キー</th>
		<th>型</th>
		<th>初期値</th>
		<th>説明</th>
	</tr>
	<tr>
		<td><code>prefix</code></td>
		<td>String</td>
		<td><code>'form'</code></td>
		<td>フォームの接頭辞。各フォームの要素の <code>name</code> と <code>id</code> の前に付きます。</td>
	</tr>
	<tr>
		<td><code>ajax</code></td>
		<td>Bool</td>
		<td><code>true</code></td>
		<td>Ajax で POST を処理するかどうかを指定します。Ajax 側で呼び出す際は <code>_ajax_call=1</code> も POST するようにして下さい。</td>
	</tr>
	<tr>
		<td><code>mail</code></td>
		<td>Bool</td>
		<td><code>false</code></td>
		<td>結果をメールで送信するかどうかを指定します。</td>
	</tr>
	<tr>
		<td><code>nonce</code></td>
		<td>String</td>
		<td><code>''</code></td>
		<td>nonce 機能を利用する際は必ず設定してください。これは nonce の乱数値を生成する時に使用します。値は16文字のできるだけ的当なものにして下さい。<a href="http://www.graviness.com/temp/pw_creator/" target="_blank">こちらのサイト</a>トを利用すると良いかもしれません。</td>
	</tr>
</table>




バリデーションルールの設定
==========================

新しいルールを作る
------------------

### 普通のフィールド

**例**

```php
$cf->add($name [, $required]);
```

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td><code>$name</code></td>
		<td>String</td>
		<td>項目の名前です。英数字(a-zA-Z0-9)・アンダースコア(_)・ハイフン(-)を使って下さい。&lt;input type="checkbox"&gt; と &lt;select&gt; で複数の項目を選択させる場合は、名前の語尾に <code>+</code> を付けます。</td>
	</tr>
	<tr>
		<td><code>$required</code></td>
		<td>Bool</td>
		<td>必須であれば <code>true</code> を指定します。必須でないときは、<code>false</code> を指定するか、引数の省略ができます。</td>
	</tr>
</table>

### CAPTCHAを追加する

```php
$cf->add_captcha();
```

これだけです。


set_option
----------

&lt;input type="checkbox"&gt;, &lt;input type="radio"&gt;, &lt;select&gt; に、選択できる項目を設定します。

**例**

```php
$cf->add('color')->set_option(array(
	'Red',
	'Orange',
	'Yellow',
	'Green',
	'Blue',
	'Indigo',
	'Violet',
));
```

複数選択させるには、名前の語尾に ``+`` を付けます。

```php
$cf->add('color+')->set_option(array(
	'Red',
	'Orange',
	'Yellow',
	'Green',
	'Blue',
	'Indigo',
	'Violet',
));
```


required
--------

項目を必須にします。

**例**

```php
$cf->add('foo')->required(true);
```

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td>1</td>
		<td>Bool</td>
		<td>入力を必須にする場合は <code>true</code> を指定します。</td>
	</tr>
</table>

なお、次の2つは全く同じ意味です。

```php
$cf->add('foo')->required(true)
$cf->add('foo', true)
```


minlen / maxlen
---------------

``minlen`` と ``maxlen`` は入力された文字列の長さの下限と上限を指定します。

**例: minlen**

```php
$cf->add('foo')->minlen(40)
```

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td>1</td>
		<td>Number</td>
		<td>文字列の長さの下限を指定します。入力がこの値より短いとエラーがでます。</td>
	</tr>
</table>


mininum / maximum
-----------------

``mininum`` と ``maximum`` は入力された値を数値として評価し、その値の下限と上限を指定します。

**例: maximum**

```php
$cf->add('foo')->maximum(40)
```

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td>1</td>
		<td>Number</td>
		<td>値の上限を指定します。入力がこの値より大きいとエラーがでます。</td>
	</tr>
</table>


minselect / maxselect / select
------------------------------

``mininum`` と ``maximum`` は複数の値が選択できる項目の場合(&lt;input type="checkbox"&gt;, &lt;select&gt;)に、選択できる個数の下限と上限を指定します。

``select`` は単に、選択できる個数を設定します。

**例: maxselect**

```php
$cf->add('foo+')->set_option(array(
	'aeuio',
	'kakikukeko',
	'sasisuseso',
))->maxselect(2)
```

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td>1</td>
		<td>Number</td>
		<td>選択できる項目の個数の上限を指定します。選択された項目の個数がこの値より多いとエラーがでます。</td>
	</tr>
</table>


filter
------

高度なバリデーションルールを設定します。

### メールアドレス

```php
$cf->add('foo')->filter('email')
```

### 電話番号

```php
$cf->add('foo')->filter('tel')
```

### URL

```php
$cf->add('foo')->filter('url')
```

### 全てひらがな / 全てカタカナ

```php
$cf->add('foo')->filter('hiragana')
$cf->add('foo')->filter('katakana')
```

### 正当な日付・時刻

第2引数にフォーマット文字列を渡します。
フォーマットについては、[PHP のマニュアル](http://www.php.net/manual/ja/datetime.createfromformat.php)を参考にして下さい。

```php
$cf->add('foo')->filter('datetime', 'Y-m-d')
$cf->add('foo')->filter('datetime', 'H:i:s')
```


type
----

機能は ``filter`` とほとんど同じですが、フォームの要素の ``type`` 属性に値を設定する点において異なります。

例えば ``filter('email')`` や ``filter('tel')`` そして ``filter('url')`` は、  
こちらの ``type('email')`` と ``type('tel')`` と ``type('url')`` を使うようにして下さい。

### メールアドレス

```php
$cf->add('foo')->type('email')
```

### 電話番号

```php
$cf->add('foo')->type('tel')
```

### URL

```php
$cf->add('foo')->type('url')
```


format
------

値を判定するのではなく、加工します。主にデータの正規化に利用します。

### kana

PHP の ``mb_convert_kana`` 関数を利用して、「半角」-「全角」変換を行います。
詳しいオプションについては [PHP のマニュアル](http://www.php.net/manual/ja/function.mb-convert-kana.php)を参照下さい。

```php
$cf->add('foo')->format('kana', 'asKV')
```

### datetime

``rule`` のところでも出てきましたが、入力された日付についての詳細情報を連想配列で保存します。
詳しくは、[PHP のマニュアル](http://www.php.net/manual/ja/function.date-parse-from-format.php)を参照下さい。

```php
$cf->add('foo')->format('datetime', 'Y/m/d H:i')
```




filter / format で独自関数をつかう
==================================

関数の命名規則は ``validation_filter_{name}`` です。
関数の呼び出しには、つぎの引数が渡されます。

<table>
	<tr>
		<th>引数</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td><code>$call</code></td>
		<td>String</td>
		<td>どちらの関数から呼ばれたのかを取得する際に使います。値は <code>'filter'</code> か <code>'format'</code> です。</td>
	</tr>
	<tr>
		<td><code>$val</code></td>
		<td>Mixed</td>
		<td>そのまんま値、データです。</td>
	</tr>
	<tr>
		<td><code>$arg</code></td>
		<td>Array</td>
		<td><code>filter</code> や <code>format</code> に渡された第1引数以降が配列として取得出来ます。</td>
	</tr>
</table>

**例**

- ``filter('upper')`` なら英字全部かどうかを判定する
- ``format('upper')`` なら英字を大文字に変換する

ような関数をつくってみます。

```php
function validation_filter_upper($call, $val, $args = array()) {
	if ('filter' == $call)
		return !!preg_match('|^[A-Z]+$|', $val);

	if ('format' == $call)
		return strtoupper($val);
}
```




HTML ヘルパー
=============

テキスト
--------

```php
<?php $cf->html->text('foo'); ?>
```

**生成例**

```html
<input type="text" name="cf-foo" id="cf-foo" />
```


テキストエリア
--------------

```php
<?php $cf->html->textarea('foo'); ?>
```

**生成例**

```html
<textarea name="cf-foo" id="cf-foo"></textarea>
```


チェックボックス / ラジオボタン
-------------------------------

出力がチェックボックスかラジオボタンかは、名前の語尾に ``+`` があるかどうかで自動で判断します。

```php
<ul><?php $cf->html->option('foo'); ?></ul>
<ul><?php $cf->html->option('bar+'); ?></ul>
```

**生成例**

```html
<ul>
	<li><label><input type="radio" name="cf-foo" value="0" /> Option A</label></li>
	<li><label><input type="radio" name="cf-foo" value="1" /> Option B</label></li>
	<li><label><input type="radio" name="cf-foo" value="1" /> Option C</label></li>
</ul>
<ul>
	<li><label><input type="checkbox" name="cf-bar[]" value="0" /> Option A</label></li>
	<li><label><input type="checkbox" name="cf-bar[]" value="1" /> Option B</label></li>
	<li><label><input type="checkbox" name="cf-bar[]" value="2" /> Option C</label></li>
</ul>
```


セレクトボックス
----------------

名前の語尾に ``+`` がある場合は自動で ``multiple`` 属性が追加されます。

```php
<?php $cf->html->select('foo'); ?>
<?php $cf->html->select('bar+'); ?>
```

**生成例**

```html
<select name="cf-foo" id="cf-foo">
	<option value="0">Option A</option>
	<option value="1">Option B</option>
	<option value="2">Option C</option>
</select>
<select name="cf-bar" id="cf-bar[]" multiple>
	<option value="0">Option A</option>
	<option value="1">Option B</option>
	<option value="2">Option C</option>
</select>
```

CAPTCHA の画像と入力フィールド
------------------------------

```php
<div><?php $cf->html->captcha_image(); ?></div>
<?php $cf->html->captcha(); ?>
```

**生成例**

```html
<div><img src="/path/to/captcha.php" id="captcha-image" alt="" /></div>
<input type="text" name="cf-captcha" id="cf-captcha" />
```




フォーム処理
============

メール送信
----------

初期化の時に ``'mail' => true`` を設定していることを確認する。

```php
$cf->submit($setting);
```

すべてのルールを記述したあとに、``$cf->submit()`` で連想配列を渡して設定をします。

<table>
	<tr>
		<th>キー</th>
		<th>型</th>
		<th>説明</th>
	</tr>
	<tr>
		<td><code>from</code></td>
		<td>String</td>
		<td>必須。メールの送信アドレスを指定します。これは決まりではありませんが、「表示名 <メールアドレス>」の形式で書くことを推奨します。</td>
	</tr>
	<tr>
		<td><code>cc</code></td>
		<td>String</td>
		<td>Carbon copy を指定します。</td>
	</tr>
	<tr>
		<td><code>bcc</code></td>
		<td>String</td>
		<td>Blind carbon copy を指定します。</td>
	</tr>
	<tr>
		<td><code>reply</code></td>
		<td>String</td>
		<td>Reply-to を指定します。</td>
	</tr>
	<tr>
		<td><code>to</code></td>
		<td>String</td>
		<td>必須。メールの受信先を指定します。メールアドレスを直接書くこともできますが、フォームの名前を入れればそれに宛てて送信することもできます。</td>
	</tr>
	<tr>
		<td><code>subject</code></td>
		<td>String</td>
		<td>必須。件名を指定します。</td>
	</tr>
	<tr>
		<td><code>body</code></td>
		<td>String</td>
		<td>必須。メールの本文を指定します。</td>
	</tr>
</table>

### body 内でフォームの値を使う

``add`` のときに設定した名前を ``{{`` と ``}}`` で囲うと文字列内で展開されます。

例えば

```php
$cf->add('foo')
```

の値を、メール本文内で使いたいときは、
	
	Foo is {{foo}}.

のように書きます。

#### マジック変数

フォームの値以外にも特殊な ``{{ }}`` を用意しています。

<table>
	<tr>
		<th>変数</th>
		<th>説明</th>
	</tr>
	<tr>
		<td><code>{{DATE}}</code></td>
		<td>現在の日付</td>
	</tr>
	<tr>
		<td><code>{{TIME}}</code></td>
		<td>現在の時刻</td>
	</tr>
</table>


メール以外での処理
------------------

``Form`` クラスを直接使わずに、extends して新しいクラスを作って、それを使って下さい。

```php
class MyForm extends Form {
	public function save($args = array()) {
		$data = $this->get_data();

		// process...
	}
	public function post_process() {
		$status = $this->last_status;

		// some process...
	}
}
```

