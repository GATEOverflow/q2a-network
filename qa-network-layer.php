<?php

	class qa_html_theme_layer extends qa_html_theme_base {

	// theme replacement functions

		function head_custom() {
			$this->output('<style>',str_replace('^',QA_HTML_THEME_LAYER_URLTOROOT,qa_opt('network_site_css')),'</style>');
			if(isset($this->content['form_activity'])) {
				$user = $this->network_user_sites($this->content['raw']['userid']);
				if($user)
					$this->content['form_activity']['fields']['network'] = array(
						'type' => 'static',
						'label' => qa_lang_html('network/network_sites'),
						'value' => '<SPAN CLASS="qa-uf-user-network-sites">'.$user.'</SPAN>',
					);
			}
			qa_html_theme_base::head_custom();
		}

		function post_meta($post, $class, $prefix=null, $separator='<BR/>')
		{
			if(qa_opt('network_site_enable')) {
				$uid = @$post['raw']['ouserid'] ? $post['raw']['ouserid']:$post['raw']['userid'];

				if(isset($post['who']['points']) && qa_opt('network_site_points')) {
					$points = intval(preg_replace('/[^\d.]/', '', $post['who']['points']['data']));
					$post['who']['points']['data']=$this->network_total_points($uid,$points);
				}
				if (qa_opt('network_site_icons') && isset($post['who']['points']['data'])) {
					$points = intval(preg_replace('/[^\d.]/', '', $post['who']['points']['data']));
					$post['who']['suffix'] = @$post['who']['suffix'].$this->network_user_sites($uid,@$points);
				}
			}
			qa_html_theme_base::post_meta($post, $class, $prefix, $separator);
			
		}	
			
		function q_view_buttons($q_view) {  
			qa_html_theme_base::q_view_buttons($q_view);		
			
			if($this->template != 'question' || !qa_opt('network_site_migrated_text')) return;
			
			// check if migrated
			
			$migrated = qa_db_read_one_value(
				qa_db_query_sub(
					'SELECT meta_value FROM ^postmeta WHERE meta_key=$ AND post_id=#',
					'migrated',$q_view['raw']['postid']
				),
				true
			);
			
			// show migrated box
			
			if($migrated) {
				$ms = explode('|',$migrated);
				
				
				$idx = 0;
				$title = '';
				while($idx <= (int)qa_opt('network_site_number')) {
					if(qa_opt('network_site_'.$idx.'_prefix') == $ms[0]) {
						$title = '<a href="'.qa_opt('network_site_'.$idx.'_url').'">'.qa_opt('network_site_'.$idx.'_title').'</a>';
						break;
					}
					$idx++;
				}
				if(!$title)
					return;
				
				$text = qa_lang_sub('network/migrated_from_x_y_ago_by_z',$title);
				$text = str_replace('#',qa_time_to_string(qa_opt('db_time')-(int)$ms[1]),$text);
				$text = str_replace('$','<a href="'.qa_path_html('user/'.$ms[2],null,qa_opt('site_url')).'">'.$ms[2].'</a>',$text);

				$this->output('<div id="qa-network-site-migrated">',$text,'</div>');
			}
		}
		
	// worker
	
		var $network_points;

		function network_total_points($uid,$points) {
			if(@$this->network_points[$uid]) {
				foreach($this->network_points[$uid] as $point)
					$points+=$point;
			}
			else {
				$idx = 0;
				while(qa_opt('network_site_'.$idx.'_url')) {
					$point = (int)qa_db_read_one_value(
						qa_db_query_sub(
							'SELECT points FROM '.qa_db_escape_string(qa_opt('network_site_'.$idx.'_prefix')).'userpoints WHERE userid=#',
							$uid
						),
						true
					);
					$this->network_points[$uid][$idx] = $point;
					$points += $point;
					$idx++;
				}
			}
			return number_format($points);
		}
		
		function network_user_sites($uid,$this_points=null) {
			$idx = 0;
			$html = '';
			if(qa_opt('network_site_icon_this') && $this_points) {
				$html.= '<a class="qa-network-site-icon" href="'.qa_opt('site_url').'" title="'.qa_opt('site_title').': '.($this_points==1?qa_lang_html('main/1_point'):qa_lang_html_sub('main/x_points',number_format($this_points))).'"><img width="10%" src="'.qa_opt('site_url').'favicon.ico"/></a>';
			}
			while(qa_opt('network_site_'.$idx.'_url')) {
				if(@$this->network_points[$uid]) {
						$points = @$this->network_points[$uid][$idx];
				}
				else {
					$points = (int)qa_db_read_one_value(
						qa_db_query_sub(
							'SELECT points FROM '.qa_db_escape_string(qa_opt('network_site_'.$idx.'_prefix')).'userpoints WHERE userid=#',
							$uid
						),
						true
					);
					$this->network_points[$uid][$idx] = $points;
				}
				if($points < qa_opt('network_site_min_points')) {
					$idx++;
					continue;
				}
				
				$html.= '<a class="qa-network-site-icon" href="'.qa_opt('network_site_'.$idx.'_url').'" title="'.qa_opt('network_site_'.$idx.'_title').': '.($points==1?qa_lang_html('main/1_point'):qa_lang_html_sub('main/x_points',number_format($points))).'"><img width="10%" src="'.qa_opt('network_site_'.$idx.'_url').qa_opt('network_site_'.$idx.'_icon').'"/></a>';
				$idx++;
			}
			return $html;					
		}


		function getuserfromhandle($handle) {
			require_once QA_INCLUDE_DIR.'qa-app-users.php';
			
			$publictouserid=qa_get_userids_from_public(array($handle));
			$userid=@$publictouserid[$handle];
				
			if (!isset($userid)) return;
			return $userid;
		}		
		// grab the handle from meta
		function who_to_handle($string)
		{
			preg_match( '#qa-user-link">([^<]*)<#', $string, $matches );
			return !empty($matches[1]) ? $matches[1] : null;
		}

		function body_suffix()
		{
			qa_html_theme_base::body_suffix();

			// Only inject on admin plugin pages for super admins
			if ($this->template !== 'admin' || qa_get_logged_in_level() < QA_USER_LEVEL_SUPER)
				return;

			$request = qa_request();
			if (strpos($request, 'admin/plugins') !== 0)
				return;

			// Check if a plugin options form is being shown
			if (!isset($this->content['form_plugin_options']))
				return;

			// Get network sites for the confirmation dialog
			$sites = array();
			$idx = 0;
			while (qa_opt('network_site_' . $idx . '_url')) {
				$title = qa_opt('network_site_' . $idx . '_title');
				if (strlen($title))
					$sites[] = $title;
				$idx++;
			}

			if (empty($sites))
				return;

			$sitesJs = qa_js(implode(', ', $sites));
			$applyUrl = qa_js(qa_path('network-apply-settings', null, qa_opt('site_url')));

			$this->output(<<<HTML
<script>
(function() {
	// Find the plugin options form via its wrapper div
	var wrapper = document.querySelector('.qa-part-form-plugin-options');
	var form = wrapper ? wrapper.querySelector('form') : null;
	if (!form) {
		// Fallback: look for any form inside the main content that isn't the plugins_form
		var allForms = document.querySelectorAll('.qa-main form');
		for (var i = 0; i < allForms.length; i++) {
			if (allForms[i].getAttribute('name') !== 'plugins_form') {
				form = allForms[i];
				break;
			}
		}
	}
	if (!form) return;

	var buttons = form.querySelector('.qa-form-tall-buttons');
	if (!buttons) {
		var submitBtn = form.querySelector('input[type="submit"]');
		if (submitBtn) buttons = submitBtn.parentNode;
	}
	if (!buttons) return;

	var btn = document.createElement('input');
	btn.type = 'button';
	btn.value = 'Apply to Network Sites';
	btn.className = 'qa-form-tall-button';
	btn.style.marginLeft = '10px';
	btn.style.backgroundColor = '#e67e22';
	btn.style.color = '#fff';
	btn.style.border = '1px solid #d35400';
	btn.style.cursor = 'pointer';
	btn.style.padding = '6px 16px';

	btn.onclick = function() {
		var options = {};
		var inputs = form.querySelectorAll('input[name], textarea[name], select[name]');
		var count = 0;
		for (var i = 0; i < inputs.length; i++) {
			var el = inputs[i];
			var name = el.name;
			if (!name || name === 'qa_form_security_code' || name === 'qa_click' ||
				el.type === 'submit' || el.type === 'button' || el.type === 'hidden') continue;
			if (el.type === 'checkbox') {
				options[name] = el.checked ? '1' : '0';
			} else {
				options[name] = el.value;
			}
			count++;
		}

		if (count === 0) {
			alert('No settings found to apply.');
			return;
		}

		var msg = 'Apply ' + count + ' setting(s) to network sites:\\n' + {$sitesJs} +
			'\\n\\nNote: This writes to the qa_options table on each site. ' +
			'Plugins using custom tables will not be affected.' +
			'\\n\\nThis will overwrite these settings on all network sites. Continue?';
		if (!confirm(msg)) return;

		btn.disabled = true;
		btn.value = 'Applying...';

		var xhr = new XMLHttpRequest();
		xhr.open('POST', {$applyUrl}, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function() {
			if (xhr.readyState !== 4) return;
			btn.disabled = false;
			btn.value = 'Apply to Network Sites';
			if (xhr.status === 200) {
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.error) {
						alert('Error: ' + resp.error);
					} else if (resp.results) {
						var summary = '';
						for (var r = 0; r < resp.results.length; r++) {
							summary += resp.results[r].site + ': ' + resp.results[r].applied + ' applied';
							if (resp.results[r].errors.length > 0)
								summary += ' (' + resp.results[r].errors.length + ' errors)';
							summary += '\\n';
						}
						alert('Done!\\n\\n' + summary);
					}
				} catch(e) {
					alert('Unexpected response from server.');
				}
			} else {
				alert('Request failed (HTTP ' + xhr.status + ').');
			}
		};
		xhr.send('options=' + encodeURIComponent(JSON.stringify(options)));
	};

	buttons.appendChild(btn);
})();
</script>
HTML
			);
		}
	}

