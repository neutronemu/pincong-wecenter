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

define('IN_AJAX', TRUE);


if (!defined('IN_ANWSION'))
{
	die;
}

class ajax extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'white'; //黑名单,黑名单中的检查  'white'白名单,白名单以外的检查
		$rule_action['actions'] = array();

		return $rule_action;
	}

	public function setup()
	{
		HTTP::no_cache_header();
	}

	public function fetch_question_category_action()
	{
		if (get_setting('category_enable') != 'Y')
		{
			exit(json_encode(array()));
		}

		exit($this->model('system')->build_category_json('question', 0));
	}

	public function modify_question_action()
	{
		if (!$question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
		}

		if ($question_info['lock'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题已锁定, 不能编辑')));
		}

		if (!$this->user_info['permission']['edit_question'])
		{
			if ($question_info['published_uid'] != $this->user_id)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限编辑这个问题')));
			}
		}

		if ($_POST['do_delete'])
		{
			// 只清空不删除
			$question_content = null;
			$question_detail = null;
		}
		else
		{
			$question_content = my_trim($_POST['question_content']);

			if (cjk_strlen($question_content) < 5)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得少于 5 个字')));
			}

			if (get_setting('question_title_limit') > 0 AND cjk_strlen($question_content) > get_setting('question_title_limit'))
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得大于') . ' ' . get_setting('question_title_limit') . ' ' . AWS_APP::lang()->_t('字节')));
			}

			$question_detail = my_trim($_POST['question_detail']);
		}

		// !注: 来路检测后面不能再放报错提示
		if (!valid_post_hash($_POST['post_hash']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
		}

		// TODO: 处理 $_POST['anonymous'] 和 $_POST['category_id']
		$this->model('question')->update_question($question_info['question_id'], $question_content, $question_detail, $this->user_id, null, null);

		if ($this->user_id != $question_info['published_uid'])
		{
			$this->model('question')->add_focus_question($question_info['question_id'], $this->user_id);

			$this->model('notify')->send($this->user_id, $question_info['published_uid'], notify_class::TYPE_MOD_QUESTION, notify_class::CATEGORY_QUESTION, $question_info['question_id'], array(
				'from_uid' => $this->user_id,
				'question_id' => $question_info['question_id']
			));
		}

		if ($_POST['do_delete'])
		{
			H::ajax_json_output(AWS_APP::RSM(array(
				'url' => get_js_url('/home/explore/')
			), 1, null));
		}

		H::ajax_json_output(AWS_APP::RSM(array(
			'url' => get_js_url('/question/' . $question_info['question_id'])
		), 1, null));

	}

	public function publish_question_action()
	{
		if (!$this->user_info['permission']['publish_question'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不够')));
		}

		if ($_POST['anonymous'] AND !$this->user_info['permission']['post_anonymously'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能匿名')));
		}

		$later = intval($_POST['later']);
		if ($later)
		{
			if (!$this->user_info['permission']['post_later'])
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能延迟发布')));
			}

			if ($later < 10 OR $later > 1440)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('延迟时间只能在 10 ~ 1400 分钟之间')));
			}
		}

		$question_content = my_trim($_POST['question_content']);
		if (!$question_content)
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请输入问题标题')));
		}

		if (!check_repeat_submission($question_content))
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请不要重复提交')));
		}

		if (!$this->model('currency')->check_balance_for_operation($this->user_info['currency'], 'currency_system_config_new_question'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余%s已经不足以进行此操作', get_setting('currency_name'))));
		}

		if (!$this->model('publish')->check_question_limit_rate($this->user_id, $this->user_info['permission']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你今天发布的问题已经达到上限')));
		}

		if (get_setting('category_enable') == 'N')
		{
			$_POST['category_id'] = 1;
		}

		// TODO: 检查 $_POST['category_id']
		if (!$_POST['category_id'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择问题分类')));
		}

		// TODO: 在管理后台添加字数选项
		if (cjk_strlen($question_content) < 5)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题标题字数不得少于 5 个字')));
		}

		if (get_setting('question_title_limit') > 0 AND cjk_strlen($question_content) > get_setting('question_title_limit'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得大于 %s 字节', get_setting('question_title_limit'))));
		}

		$question_detail = my_trim($_POST['question_detail']);

		// TODO: 在管理后台添加字数选项
		if (cjk_strlen($question_detail) > 50000)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题字数不得多于 50000 字')));
		}

		if ($_POST['topics'])
		{
			$topic_title_limit = intval(get_setting('topic_title_limit'));
			foreach ($_POST['topics'] AS $key => $topic_title)
			{
				$topic_title = my_trim($topic_title);

				if (!$topic_title)
				{
					unset($_POST['topics'][$key]);
				}
				else
				{
					if ($topic_title_limit AND cjk_strlen($topic_title) > $topic_title_limit AND !$this->model('topic')->get_topic_id_by_title($topic_title))
					{
						H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('话题标题字数不得超过 %s 字', $topic_title_limit)));
						break;
					}
					$_POST['topics'][$key] = $topic_title;
				}
			}

			if (get_setting('question_topics_limit') AND sizeof($_POST['topics']) > get_setting('question_topics_limit'))
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('单个问题话题数量最多为 %s 个, 请调整话题数量', get_setting('question_topics_limit'))));
			}
		}

		if (!$_POST['topics'] AND get_setting('new_question_force_add_topic') == 'Y')
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请为问题添加话题')));
		}

		// !注: 来路检测后面不能再放报错提示
		if (!valid_post_hash($_POST['post_hash']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
		}

		set_repeat_submission_digest($question_content);

		if ($later)
		{
			// 延迟显示
			$this->model('publish')->schedule(
				'question',
				real_time() + $later * 60 + rand(-30, 30),
				$question_content,
				$question_detail,
				$this->user_id,
				$_POST['anonymous'],
				$_POST['category_id'],
				array(
					'topics' => $_POST['topics'],
					'ask_user_id' => $_POST['ask_user_id'],
					'permission_create_topic' => $this->user_info['permission']['create_topic']
				)
			);

			$url = get_js_url('/publish/delay_display/');
		}
		else
		{
			$question_id = $this->model('publish')->publish_question(
				$question_content,
				$question_detail,
				$_POST['category_id'],
				$this->user_id,
				$_POST['topics'],
				$_POST['anonymous'],
				$_POST['ask_user_id'],
				$this->user_info['permission']['create_topic']
			);

			$url = get_js_url('/question/' . $question_id);
		}

		H::ajax_json_output(AWS_APP::RSM(array('url' => $url), 1, null));
	}

	public function publish_article_action()
	{
		if (!$this->user_info['permission']['publish_article'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不够')));
		}

		if ($_POST['anonymous'] AND !$this->user_info['permission']['post_anonymously'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能匿名')));
		}

		$later = intval($_POST['later']);
		if ($later)
		{
			if (!$this->user_info['permission']['post_later'])
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能延迟发布')));
			}

			if ($later < 10 OR $later > 1440)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('延迟时间只能在 10 ~ 1400 分钟之间')));
			}
		}

		$article_title = my_trim($_POST['title']);
		if (!$article_title)
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请输入文章标题')));
		}

		if (!check_repeat_submission($article_title))
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请不要重复提交')));
		}

		if (!$this->model('currency')->check_balance_for_operation($this->user_info['currency'], 'currency_system_config_new_article'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余%s已经不足以进行此操作', get_setting('currency_name'))));
		}

		if (!$this->model('publish')->check_article_limit_rate($this->user_id, $this->user_info['permission']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你今天发布的文章已经达到上限')));
		}

		if (get_setting('category_enable') == 'N')
		{
			$_POST['category_id'] = 1;
		}

		// TODO: 检查 $_POST['category_id']
		if (!$_POST['category_id'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择文章分类')));
		}

		if (get_setting('question_title_limit') > 0 AND cjk_strlen($article_title) > get_setting('question_title_limit'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章标题字数不得大于 %s 字节', get_setting('question_title_limit'))));
		}

		$article_content = my_trim($_POST['message']);

		// TODO: 在管理后台添加字数选项
		if (cjk_strlen($article_content) > 50000)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('文章字数不得多于 50000 字')));
		}

		if ($_POST['topics'])
		{
			$topic_title_limit = intval(get_setting('topic_title_limit'));
			foreach ($_POST['topics'] AS $key => $topic_title)
			{
				$topic_title = my_trim($topic_title);

				if (!$topic_title)
				{
					unset($_POST['topics'][$key]);
				}
				else
				{
					if ($topic_title_limit AND cjk_strlen($topic_title) > $topic_title_limit AND !$this->model('topic')->get_topic_id_by_title($topic_title))
					{
						H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('话题标题字数不得超过 %s 字', $topic_title_limit)));
						break;
					}
					$_POST['topics'][$key] = $topic_title;
				}
			}

			if (get_setting('question_topics_limit') AND sizeof($_POST['topics']) > get_setting('question_topics_limit'))
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('单个文章话题数量最多为 %s 个, 请调整话题数量', get_setting('question_topics_limit'))));
			}
		}
		if (get_setting('new_question_force_add_topic') == 'Y' AND !$_POST['topics'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请为文章添加话题')));
		}

		// !注: 来路检测后面不能再放报错提示
		if (!valid_post_hash($_POST['post_hash']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
		}

		set_repeat_submission_digest($article_title);

		if ($later)
		{
			// 延迟显示
			$this->model('publish')->schedule(
				'article',
				real_time() + $later * 60 + rand(-30, 30),
				$article_title,
				$article_content,
				$this->user_id,
				$_POST['anonymous'],
				$_POST['category_id'],
				array(
					'topics' => $_POST['topics'],
					'permission_create_topic' => $this->user_info['permission']['create_topic']
				)
			);

			$url = get_js_url('/publish/delay_display/');
		}
		else
		{
			$article_id = $this->model('publish')->publish_article(
				$article_title,
				$article_content,
				$this->user_id,
				$_POST['topics'],
				$_POST['category_id'],
				$this->user_info['permission']['create_topic'],
				$_POST['anonymous']
			);

			$url = get_js_url('/article/' . $article_id);
		}

		H::ajax_json_output(AWS_APP::RSM(array('url' => $url), 1, null));
	}

	public function modify_article_action()
	{
		if (!$article_info = $this->model('article')->get_article_info_by_id($_POST['article_id']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章不存在')));
		}

		if ($article_info['lock'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章已锁定, 不能编辑')));
		}

		if (!$this->user_info['permission']['edit_article'])
		{
			if ($article_info['uid'] != $this->user_id)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限编辑这个文章')));
			}
		}

		if ($_POST['do_delete'])
		{
			// 只清空不删除
			$article_title = null;
			$article_content = null;
		}
		else
		{
			$article_title = my_trim($_POST['title']);

			if (!$article_title)
			{
				H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请输入文章标题')));
			}

			if (get_setting('question_title_limit') > 0 AND cjk_strlen($article_title) > get_setting('question_title_limit'))
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章标题字数不得大于') . ' ' . get_setting('question_title_limit') . ' ' . AWS_APP::lang()->_t('字节')));
			}

			$article_content = my_trim($_POST['message']);
		}

		// !注: 来路检测后面不能再放报错提示
		if (!valid_post_hash($_POST['post_hash']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
		}

		// TODO: 处理 $_POST['anonymous'] 和 $_POST['category_id']
		$this->model('article')->update_article($article_info['id'], $this->user_id, $article_title, $article_content, null, null);

		if ($_POST['do_delete'])
		{
			H::ajax_json_output(AWS_APP::RSM(array(
				'url' => get_js_url('/home/explore/')
			), 1, null));
		}

		H::ajax_json_output(AWS_APP::RSM(array(
			'url' => get_js_url('/article/' . $article_info['id'])
		), 1, null));
	}


	public function publish_video_action()
	{
		if (!$this->user_info['permission']['publish_video'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不够')));
		}

		if ($_POST['anonymous'] AND !$this->user_info['permission']['post_anonymously'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能匿名')));
		}

		$later = intval($_POST['later']);
		if ($later)
		{
			if (!$this->user_info['permission']['post_later'])
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的等级还不能延迟发布')));
			}

			if ($later < 10 OR $later > 1440)
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('延迟时间只能在 10 ~ 1400 分钟之间')));
			}
		}

		$video_url_info = Services_VideoParser::parse_video_url($_POST['video_webpage_url']);
		if (!$video_url_info)
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('无法识别视频来源')));
		}

		$video_title = my_trim($_POST['title']);
		if (!$video_title)
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请输入标题')));
		}

		if (!check_repeat_submission($video_title))
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请不要重复提交')));
		}

		if (!$this->model('currency')->check_balance_for_operation($this->user_info['currency'], 'currency_system_config_new_video'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余%s已经不足以进行此操作', get_setting('currency_name'))));
		}

		if (!$this->model('publish')->check_video_limit_rate($this->user_id, $this->user_info['permission']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你今天发布的投稿已经达到上限')));
		}

		if (get_setting('category_enable') == 'N')
		{
			$_POST['category_id'] = 1;
		}

		// TODO: 检查 $_POST['category_id']
		if (!$_POST['category_id'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择分类')));
		}

		if (get_setting('question_title_limit') > 0 AND cjk_strlen($video_title) > get_setting('question_title_limit'))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('标题字数不得大于 %s 字节', get_setting('question_title_limit'))));
		}

		$video_content = my_trim($_POST['message']);

		// TODO: 在管理后台添加字数选项
		if (cjk_strlen($video_content) > 1000)
		{
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('字数不得多于 1000 字')));
		}

		if ($_POST['topics'])
		{
			$topic_title_limit = intval(get_setting('topic_title_limit'));
			foreach ($_POST['topics'] AS $key => $topic_title)
			{
				$topic_title = my_trim($topic_title);

				if (!$topic_title)
				{
					unset($_POST['topics'][$key]);
				}
				else
				{
					if ($topic_title_limit AND cjk_strlen($topic_title) > $topic_title_limit AND !$this->model('topic')->get_topic_id_by_title($topic_title))
					{
						H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('话题标题字数不得超过 %s 字', $topic_title_limit)));
						break;
					}
					$_POST['topics'][$key] = $topic_title;
				}
			}

			if (get_setting('question_topics_limit') AND sizeof($_POST['topics']) > get_setting('question_topics_limit'))
			{
				H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('单个话题数量最多为 %s 个, 请调整话题数量', get_setting('question_topics_limit'))));
			}
		}
		if (get_setting('new_question_force_add_topic') == 'Y' AND !$_POST['topics'])
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请添加话题')));
		}

		// TODO: why?
		// !注: 来路检测后面不能再放报错提示
		if (!valid_post_hash($_POST['post_hash']))
		{
			H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
		}

		// 开销大的操作放在最后
		// 从视频网站取得元数据, 如时长
		// 以 'https://www.youtube.com/watch?v=abcdefghijk' 为例
		// source_type 指视频网站, 如 'youtube'
		// source 指该视频在所在网站上的 id, 如 'abcdefghijk'
		$parser_result = Services_VideoParser::fetch_video_metadata($video_url_info['source_type'], $video_url_info['source']);
		if (!$parser_result)
		{
			H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('无法解析视频')));
		}

		set_repeat_submission_digest($video_title);

		if ($later)
		{
			// 延迟显示
			$this->model('publish')->schedule(
				'video',
				real_time() + $later * 60 + rand(-30, 30),
				$video_title,
				$video_content,
				$this->user_id,
				$_POST['anonymous'],
				$_POST['category_id'],
				array(
					'topics' => $_POST['topics'],
					'permission_create_topic' => $this->user_info['permission']['create_topic'],
					'source_type' => $parser_result['source_type'],
					'source' => $parser_result['source'],
					'duration' => $parser_result['duration']
				)
			);

			$url = get_js_url('/publish/delay_display/');
		}
		else
		{
			$video_id = $this->model('publish')->publish_video(
				$video_title,
				$video_content,
				$this->user_id,
				$parser_result['source_type'],
				$parser_result['source'],
				$parser_result['duration'],
				$_POST['topics'],
				$_POST['category_id'],
				$this->user_info['permission']['create_topic'],
				$_POST['anonymous']
			);

			$url = get_js_url('/v/' . $video_id);
		}

		H::ajax_json_output(AWS_APP::RSM(array('url' => $url), 1, null));
	}

}
