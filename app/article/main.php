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
		$rule_action['rule_type'] = 'white';

		if ($this->user_info['permission']['visit_site'])
		{
			$rule_action['actions'] = array(
				'index'
			);
		}

		return $rule_action;
	}

	public function index_action()
	{
		if ($this->user_id AND $_GET['notification_id'])
		{
			$this->model('notification')->mark_as_read($_GET['notification_id'], $this->user_id);
		}

		$item_id = intval($_GET['item_id']);
		if ($item_id)
		{
			if (!$reply = $this->model('article')->get_article_comment_by_id($item_id))
			{
				HTTP::error_404();
			}
			$_GET['id'] = $reply['article_id'];
		}

		if (!$thread_info = $this->model('article')->get_article_by_id($_GET['id']))
		{
			HTTP::error_404();
		}

		$replies_per_page = S::get_int('replies_per_page');
		if (!$replies_per_page)
		{
			$replies_per_page = 100;
		}

		$url_param[] = 'id-' . $thread_info['id'];

		if ($_GET['sort'] == 'DESC')
		{
			$sort = 'DESC';

			$url_param[] = 'sort-DESC';
		}
		else
		{
			$sort = 'ASC';
		}

		if ($_GET['fold'])
		{
			$order_by[] = "fold ASC";

			$url_param[] = 'fold-1';
		}

		if ($_GET['sort_key'] == 'agree_count')
		{
			$order_by[] = "reputation " . $sort;
			$order_by[] = "agree_count " . $sort;

			$url_param[] = 'sort_key-agree_count';
		}
		else
		{
			$order_by[] = "id " . $sort;
		}

		if ($this->user_id)
		{
			$thread_info['vote_value'] = $this->model('vote')->get_user_vote_value_by_id('article', $thread_info['id'], $this->user_id);
		}

		TPL::assign('article_info', $thread_info);
		if ($thread_info['redirect_id'])
		{
			TPL::assign('redirect_info', $this->model('content')->get_post_by_id('article', $thread_info['redirect_id']));
		}
		if ($_GET['rf'])
		{
			TPL::assign('redirected_from', $this->model('content')->get_post_by_id('article', $_GET['rf']));
		}

		$page_title = CF::page_title($thread_info['user_info'], 'article_' . $thread_info['id'], $thread_info['title']);
		$this->crumb($page_title);

		$reply_count = $thread_info['comments'];
		// 判断是否已合并
		if ($redirect_posts = $this->model('content')->get_redirect_posts('article', $thread_info['id']))
		{
			foreach ($redirect_posts AS $key => $val)
			{
				$post_ids[] = $val['id'];
				// 修复合并后回复数
				$reply_count += $val['comments'];
			}
		}
		$post_ids[] = $thread_info['id'];

		if ($item_id)
		{
			$comments[] = $reply;
		}
		else
		{
			$comments = $this->model('article')->get_article_comments($post_ids, $_GET['page'], $replies_per_page, implode(', ', $order_by));
		}

		if ($comments AND $this->user_id)
		{
			$comment_ids = array();
			foreach ($comments as $comment)
			{
				$comment_ids[] = $comment['id'];
			}

			$comment_vote_values = $this->model('vote')->get_user_vote_values_by_ids('article_comment', $comment_ids, $this->user_id);

			foreach ($comments AS $key => $val)
			{
				// 当前用户评论点赞状态
				$comments[$key]['vote_value'] = $comment_vote_values[$val['id']];

				//$comments[$key]['message'] = $this->model('mention')->parse_at_user($val['message']);
			}
		}

		if ($this->user_id)
		{
			TPL::assign('user_follow_check', $this->model('follow')->user_follow_check($this->user_id, $thread_info['uid']));
		}

		TPL::assign('question_related_list', $this->model('question')->get_related_question_list(null, $thread_info['title']));

		$this->model('content')->update_view_count('article', $thread_info['id']);

		TPL::assign('comments', $comments);
		TPL::assign('comments_count', $reply_count);

		TPL::assign('pagination', AWS_APP::pagination()->create(array(
			'base_url' => url_rewrite('/article/') . implode('__', $url_param),
			'total_rows' => $reply_count,
			'per_page' => $replies_per_page
		)));

		TPL::set_meta('keywords', implode(',', $this->model('system')->analysis_keyword($thread_info['title'])));

		TPL::set_meta('description', $thread_info['title'] . ' - ' . truncate_text(str_replace("\r\n", ' ', $thread_info['message']), 128));

		if (S::get('advanced_editor_enable') == 'Y')
		{
			import_editor_static_files();
		}

		$topic_ids = $this->model('topic')->get_topic_ids_by_item_id($thread_info['id'], 'article');
		if ($topic_ids)
		{
			TPL::assign('topics', $this->model('topic')->get_topics_by_ids($topic_ids));
		}

		$recommend_posts = $this->model('posts')->get_recommend_posts_by_topic_ids($topic_ids);
		if ($recommend_posts)
		{
			foreach ($recommend_posts as $key => $value)
			{
				if ($value['post_type'] == 'article' AND $value['id'] == $thread_info['id'])
				{
					unset($recommend_posts[$key]);
					break;
				}
			}
			TPL::assign('recommend_posts', $recommend_posts);
		}

		if ($this->user_id)
		{
			TPL::assign('following', $this->model('postfollow')->is_following('article', $thread_info['id'], $this->user_id));
		}

		TPL::output('article/index');
	}

}
