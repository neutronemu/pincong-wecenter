<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by Tatfook Network Team
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/

define('IN_AJAX', TRUE);

if (!defined('IN_ANWSION'))
{
	die;
}

class ajax extends AWS_CONTROLLER
{
	public function setup()
	{
		H::no_cache_header();
	}

	public function remove_article_action()
	{
		if (!$this->user_info['permission']['is_moderator'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', _t('对不起, 你没有删除文章的权限')));
		}

		if ($article_info = $this->model('content')->get_thread_info_by_id('article', H::POST('article_id')))
		{
			$this->model('article')->clear_article($article_info['id'], null);
		}

		H::ajax_json_output(AWS_APP::RSM(array(
			'url' => url_rewrite('/')
		), 1, null));
	}

}