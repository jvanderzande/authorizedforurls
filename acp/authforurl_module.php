<?php
/**
*
* Authorized for urls extension for the phpBB Forum Software package.
*
* @copyright (c) 2016 Rich McGirr (RMcGirr83)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace rmcgirr83\authorizedforurls\acp;

class authforurl_module
{
	var $u_action;

	function main($id, $mode)
	{

		global $language, $request, $template;
		global $config, $phpbb_container;

		$this->tpl_name = 'acp_authforurl';
		$this->page_title = $language->lang('AFU_ACP_TITLE');

		$form_name = 'acp_afu';
		add_form_key($form_name);

		$error = '';

		$config_text = $phpbb_container->get('config_text');

		$authforurl_data = $config_text->get_array([
			'authforurl_tlds',
		]);

		//convert the string to an array
		$authurl_array = explode(',', $authforurl_data['authforurl_tlds']);

		// remove spaces
		$authurl_array = array_map('trim', $authurl_array);

		//sort the array
		sort($authurl_array);

		// put the array back into a string
		$authurl_array = implode(',', $authurl_array);


		$authforwords_data = $config_text->get_array([
			'authforwords',
		]);

		//convert the string to an array
		if (isset($authforwords_data['authforwords'])) {
			$authwords_array = explode(',', $authforwords_data['authforwords']);
		} else {
			$authwords_array = ["coupon*", "gambl*", "cheap*", "discount*", "buy*", "rabat*", "shein", "temu"];
		}

		// remove spaces
		$authwords_array = array_map('trim', $authwords_array);

		//sort the array
		sort($authwords_array);

		// put the array back into a string
		$authwords_array = implode(',', $authwords_array);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_name))
			{
				$error = $language->lang('FORM_INVALID');
			}

			if (empty($error))
			{
				$config->set('authforurl_img_bbcode', $request->variable('authforurl_img_bbcode', false));
				$config->set('authforurl_email', $request->variable('authforurl_email', false));
				$config->set('authforurl_deny_post', $request->variable('authforurl_deny_post', true));

				$config_text->set_array([
					'authforurl_tlds'			=> $request->variable('authforurl_tlds', '', true),
				]);

				$config_text->set_array([
					'authforwords'			=> $request->variable('authforwords', '', true),
				]);

				$config->set('authforwordcnt', $request->variable('authforwordcnt', 3));

				trigger_error($language->lang('AFU_SAVED') . adm_back_link($this->u_action));
			}
		}

		$template->assign_vars([
			'ERRORS'			=> $error,
			'AFU_BBCODE'		=> $config['authforurl_img_bbcode'],
			'AFU_EMAIL'			=> $config['authforurl_email'],
			'AFU_DENY_POST'		=> $config['authforurl_deny_post'],
			'AFU_TLDS'			=> $authurl_array,
			'AFU_WORDS'			=> $authwords_array,
			'AFU_WORDCNT'		=> $config['authforwordcnt'],

			'U_ACTION'			=> $this->u_action,
		]);
	}
}
