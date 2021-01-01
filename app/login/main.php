<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/

if (!defined('IN_ANWSION'))
{
	die;
}

class main extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'black';

		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();

		if ($this->user_id)
		{
			HTTP::redirect('/');
		}
	}

	public function index_action()
	{
		if ($_POST['username'])
		{
			$this->next_action();
			return;
		}

		$this->crumb(AWS_APP::lang()->_t('登录'));

		TPL::import_css('css/register.css');

		TPL::assign('token', AWS_APP::form()->create_csrf_token(600, 'login_index'));
		TPL::assign('captcha_required', $this->model('login')->is_captcha_required());

		TPL::output("account/login");
	}

	public function next_action()
	{
		if (!check_http_referer())
		{
			H::redirect_msg(AWS_APP::lang()->_t('错误的请求'), '/');
		}

		if (!AWS_APP::form()->check_csrf_token($_POST['token'], 'login_index'))
		{
			H::redirect_msg(AWS_APP::lang()->_t('页面停留时间过长, 请刷新页面重试'), '/login/');
		}

		$captcha_required = $this->model('login')->is_captcha_required();

		// 检查验证码
		if ($captcha_required)
		{
			if ($_POST['captcha_enabled'] == '0')
			{
				H::redirect_msg(AWS_APP::lang()->_t('请填写验证码'), '/login/');
			}
			if (!AWS_APP::captcha()->is_valid($_POST['captcha'], H::get_cookie('captcha')))
			{
				H::redirect_msg(AWS_APP::lang()->_t('请填写正确的验证码'), '/login/');
			}
		}

		if (!$user_info = $this->model('account')->get_user_info_by_username($_POST['username']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('用户名不存在'), '/login/');
		}

		$this->crumb(AWS_APP::lang()->_t('登录'));

		TPL::import_css('css/register.css');

		TPL::import_js('js/bcrypt.js');
		if ($user_info['password_version'] < 2)
		{
			TPL::import_js('js/md5.js');
			TPL::assign('client_salt', $this->model('password')->generate_client_salt());
		}

		TPL::assign('token', AWS_APP::form()->create_csrf_token(600, 'login_next'));
		TPL::assign('captcha_required', $captcha_required);
		TPL::assign('user', $user_info);

		TPL::output("account/login_next");
	}

}