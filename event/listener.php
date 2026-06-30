<?php
/**
*
* Authorized for urls extension for the phpBB Forum Software package.
*
* @copyright (c) 2016 Rich McGirr (RMcGirr83)
* @copyright (c) 2018, kasimi, https://kasimi.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace rmcgirr83\authorizedforurls\event;

/* Ignore */
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\config\db_text as config_text;
use phpbb\language\language;
use phpbb\template\template;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var auth $auth */
	protected $auth;

	/** @var config $config */
	protected $config;

	/** @var config_text  $config_text */
	protected $config_text;

	/** @var language $language */
	protected $language;

	/** @var template $template*/
	protected $template;

	/* @var \rmcgirr83\topicdescription\event\listener */
	protected $topicdescription;

	/**
	* Constructor
	* NOTE: The parameters of this method must match in order and type with
	* the dependencies defined in the services.yml file for this service.
	*
	* @param auth			$auth			Auth object
	* @param config			$config			Config object
	* @param config_text	$config_text	Config text object
	* @param language		$language		Language object
	* @param template		$template		Template object
	* @param \rmcgirr83\topicdescription\event\listener	$topic_description	Topic description extension
	*/
	public function __construct(
		auth $auth,
		config $config,
		config_text $config_text,
		language $language,
		template $template,
		\rmcgirr83\topicdescription\event\listener $topicdescription = null)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->config_text = $config_text;
		$this->language = $language;
		$this->template = $template;
		$this->topicdescription = $topicdescription;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return [
			'core.acp_extensions_run_action_after'		=>	'acp_extensions_run_action_after',
			'core.permissions'							=> 'add_permission',
			'core.posting_modify_message_text'			=> 'posting_modify_message_text',
			'core.posting_modify_submit_post_before'	=> 'posting_modify_submit_post_before',
			'core.ucp_profile_modify_signature'			=> 'modify_signature',
			'core.ucp_pm_compose_modify_parse_before'	=> 'modify_message_text',
			// for topic description extension
			'core.posting_modify_submission_errors' 	=> 'modify_submission_errors',
		];
	}

	/* Display additional metdate in extension details
	*
	* @param $event			event object
	* @param return null
	* @access public
	*/
	public function acp_extensions_run_action_after($event)
	{
		if ($event['ext_name'] == 'rmcgirr83/authorizedforurls' && $event['action'] == 'details')
		{
			$this->template->assign_var('S_BUY_ME_A_BEER_AUTHORIZEDFORURLS', true);
		}
	}

	/**
	* Add administrative permissions to manage forums
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_post_url'] = ['lang' => 'ACL_U_POST_URL', 'cat' => 'post'];
		$event['permissions'] = $permissions;
	}

	public function posting_modify_message_text($event)
	{
		if ($event['submit'] || $event['preview'])
		{
			$message = $event['message_parser'];
			$check_text = $this->check_text($message->message);
			$check_subject = $this->check_text($event['post_data']['post_subject']);
			if ((!empty($check_text) || !empty($check_subject)) && $this->config['authforurl_deny_post'])
			{
				$message->warn_msg[] = (!empty($check_text)) ? $check_text : $check_subject;
			}
			$event['message_parser'] = $message;
		}
	}

	public function posting_modify_submit_post_before($event)
	{
		$mode = $event['mode'];
		$data = $event['data'];
		$message = $data['message'];
		$check_text = $this->check_text($message);

		if (!empty($check_text) && !$this->config['authforurl_deny_post'])
		{
			if (in_array($mode, ['post','quote','reply']))
			{
				$data['force_visibility'] = $data['force_approved_state'] = ITEM_UNAPPROVED;
			}
			else if ($mode == 'edit')
			{
				$data['force_visibility'] = ITEM_REAPPROVE;
				$data['force_approved_state'] = ITEM_UNAPPROVED;
			}
		}
		$event['data'] = $data;
	}

	public function modify_signature($event)
	{
		if ($event['submit'] || $event['preview'])
		{
			$error = $event['error'];
			$check_text = $this->check_text($event['signature']);
			if (!empty($check_text))
			{
				$error[] = $check_text;
			}
			$event['error'] = $error;
		}
	}

	public function modify_message_text($event)
	{
		if ($event['submit'] || $event['preview'])
		{
			$message = $event['message_parser'];
			$check_text = $this->check_text($message->message);
			if (!empty($check_text))
			{
				$message->warn_msg[] = $check_text;
			}
			$event['message_parser'] = $message;
		}
	}

	public function modify_submission_errors($event)
	{
		$topic_desc = ($this->topicdescription !== null) ? $event['post_data']['topic_desc'] : '';
		if (!empty($topic_desc))
		{
			$error = $event['error'];
			$check_text = $this->check_text($topic_desc);
			if (!empty($check_text))
			{
				if ($this->config['authforurl_deny_post'])
				{
					$error[] = $check_text;
				}
			}
			$event['error'] = $error;
		}
	}

	public function check_text($check_text, $return_lang_args = false)
	{
		if (!$this->auth->acl_get('u_post_url'))
		{
			// The following will allow img bbcode and email links to not be checked per the setting in the ACP
			// eg if $check_email = false, then emails (eg whatever@whatever.com, etc)
			// will not be checked for
			$check_email = $this->config['authforurl_email'];
			$check_img_bbcode = $this->config['authforurl_img_bbcode'];

			$tld_list = $this->config_text->get_array([
				'authforurl_tlds',
			]);

			//convert the string to an array
			$tld_list = explode(',', $tld_list['authforurl_tlds']);

			// remove spaces
			$tld_list = array_map('trim', $tld_list);

			//convert the array back into a string
			$tld_list = implode('|', $tld_list);

			// thanks for the regex tut A_Jelly_Doughnut!! :)
			// we want emails to show
			if (!$check_email)
			{
				$check_text = preg_replace("#([a-z0-9\-_]+)@(((?:www.)?\b[a-z0-9\-_]+)\.($tld_list)(\.($tld_list))?\b)#i",'',$check_text);
			}
			// we want img bbcode tags to show
			if (!$check_img_bbcode)
			{
				$check_text = preg_replace("/\[img\s*\](.+?)\[\/img\]/i", '',$check_text);
			}
			// we want internal links removed
			$check_text = str_ireplace($this->config['cookie_domain'], '', $check_text);

			// check the whole darn thang now for any TLD's
			// at least those that >seem< to match from the array
			// and have not been excluded above
			preg_match_all("#(([a-z0-9\-_]+)@)?([a-z]{3,6}://)?(((?:www.)?\b[a-z0-9\-_]+)\.($tld_list)(\.($tld_list))?\b)#i", $check_text, $match);

			// we have a match..uhoh, someone's being naughty
			// time to slap 'em up side the head
			if (!empty(array_keys($match[0])))
			{
				$this->language->add_lang('common', 'rmcgirr83/authorizedforurls');

				$type = '';
				if ($check_img_bbcode)
				{
					$type .= $this->language->lang('AUTHED_IMAGES');
				}
				if ($check_email)
				{
					$type .= (!empty($type)) ? ', ' .  $this->language->lang('AUTHED_EMAIL') : $this->language->lang('AUTHED_EMAIL');
				}
				$type .= (!empty($type)) ? ' ' . $this->language->lang('AUTHED_OR') . ' ' . $this->language->lang('AUTHED_URL') : $this->language->lang('AUTHED_URL');

				$matched_url = '';
				$last_key = array_key_last($match[0]);
				$first_key = array_key_first($match[0]);
				$match_count = count($match[0]);

				foreach ($match[0] as $key => $value)
				{
					if ($key == $first_key)
					{
						$matched_url .= $value;
					}
					else if ($match_count > 2 && $key != $last_key)
					{
						$matched_url .= $this->language->lang('COMMA_SEPARATOR') . $value;
					}
					else if ($key == $last_key)
					{
						$matched_url .= $this->language->lang('AUTHED_AND') . $value;
					}
				}

				if ($return_lang_args)
				{
					return array('URL_UNAUTHED', $type, $matched_url);
				}

				return $this->language->lang('URL_UNAUTHED', $type, $matched_url);
			}

			//  ============= added words check ==============================================
			$word_list = $this->config_text->get_array([
				'authforwords',
			]);

			// get the allowed word count
			$allowed_word_count = (int) $this->config['authforwordcnt'];

			// convert the string to an array
			$word_list = explode(',', $word_list['authforwords']);
			// remove spaces
			$word_list = array_map('trim', $word_list);
			// convert the array back into a string
			$word_list = implode('|', $word_list);
			// convert wildcards to regex
			$word_list = str_replace('*', '\w*', $word_list);
			$word_list = str_replace('?', '\w', $word_list);

			// Check if any words needs testing
			// Skip check when allowedwords is 0
 			if (trim($word_list) === '' || $allowed_word_count < 1) {
				return false;
			}

			// check the whole darn thang now for any WORD's
			// at least those that >seem< to match from the array
			// and have not been excluded above
			$matchCount = preg_match_all("#(?<![A-Za-z0-9_])($word_list)(?![A-Za-z0-9_])#i", $check_text, $match_word);
			// we have a match..uhoh, someone's being naughty
			// $matchtxt = implode('|', $match_word);
			// time to slap 'em up side the head and array entries are > 2

			if (!empty(array_keys($match_word[0])) && $matchCount > $allowed_word_count)
			{
				$this->language->add_lang('common', 'rmcgirr83/authorizedforurls');
				// Fill string with unique matched words and their count
				$counts = array_count_values(array_map('strtolower', $match_word[0]));
				$matchtxt="";
				// $matchtxt = implode('|', array_map(
				// 	fn($word, $count) => "$word=$count",
				// 	array_keys($counts),
				// 	$counts
				// ));

				if ($return_lang_args)
				{
					return array('WORD_UNAUTHED', $matchCount, $matchtxt);
				}

				return $this->language->lang('WORD_UNAUTHED', $matchCount, $matchtxt);
			}
		}

		return false;
	}
}
