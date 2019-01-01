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

class api extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'white';

		$rule_action['actions'] = array(
			'get_video_metadata',
			'save_danmaku',
			'remove_danmaku'
		);

		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();
	}

	public function get_video_metadata_action()
	{
		if (!$video_info = $this->model('video')->get_video_info_by_id($_POST['video_id']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('投稿不存在')));
		}

		$metadata = Services_VideoParser::fetch_video_metadata($video_info['source_type'], $video_info['source']);
		if (!$metadata)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('无法解析视频')));
		}

		H::ajax_json_output(AWS_APP::RSM($metadata, 1, null));
	}

	public function save_danmaku_action()
	{
		if (!$this->user_info['permission']['publish_comment'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不够')));
		}

		if (!$video_info = $this->model('video')->get_video_info_by_id($_POST['video_id']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('投稿不存在')));
		}

		$stime = intval($_POST['stime']); // 毫秒
		$dur = $video_info['duration'] * 1000; // 秒 * 1000

		if ($stime < 0 OR $stime >= $dur)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('数据错误')));
		}

		$text = my_trim($_POST['text']);

		if (!$text)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('数据错误')));
		}

		// TODO: 在管理后台添加字数选项
		if (cjk_strlen($text) > 100)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('字数不得多于 100 字')));
		}

		if (!check_repeat_submission($text))
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请不要重复提交')));
		}

		// 1:滚动字幕 4:底端渐隐 5:顶端渐隐
		$mode = intval($_POST['mode']);
		if ($mode !== 4 OR $mode !== 5)
		{
			$mode = 1;
		}

		// 18:小字号 25:中字号
		$size = intval($_POST['size']);
		if ($size !== 18)
		{
			$size = 25;
		}

		$color = intval($_POST['color']);
		if ($color < 0 OR $color > 0xffffff)
		{
			$color = 0xffffff;
		}

		set_repeat_submission_digest($text);

		$this->model('danmaku')->save_danmaku(
			$video_info['id'],
			$this->user_id,
			$stime,
			$text,
			$mode,
			$size,
			$color
		);

		H::ajax_json_output(AWS_APP::RSM($metadata, 1, null));
	}

	public function remove_danmaku_action()
	{
	}


}