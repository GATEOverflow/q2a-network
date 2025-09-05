<?php

	class qa_network_widget {

		function allow_template($template)
		{
			return true;
		}

		function allow_region($region)
		{
			return true;
		}

		function output_widget($region, $place, $themeobject, $template, $request, $qa_content) {
			$themeobject->output('<h2>' . qa_lang('network/widget_title') . '</h2>');

			if (qa_opt('network_site_widget_this')) {
				$themeobject->output(
					'<ul class="network-sites">',
						'<li class="network-site-entry">',
							$this->build_site_link(qa_opt('site_url'), qa_opt('site_title'), 'favicon.ico'),
						'</li>',
					'</ul>'
				);
			}

			$idx = 0;
			while (qa_opt('network_site_' . $idx . '_url')) {
				$url = qa_opt('network_site_' . $idx . '_url');
				$title = qa_opt('network_site_' . $idx . '_title');
				$icon = qa_opt('network_site_' . $idx . '_icon');

				$themeobject->output(
					'<ul class="network-sites">',
						'<li class="network-site-entry">',
							$this->build_site_link($url, $title, $icon),
						'</li>',
					'</ul>'
				);

				$idx++;
			}
		}

		/**
		 * Builds the HTML for a site link with icon.
		 *
		 * @param string $url  The URL of the site.
		 * @param string $title The title of the site.
		 * @param string $iconPath The path to the site's icon.
		 * @return string HTML anchor element.
		 */
		function build_site_link($url, $title, $iconPath) {
			
			$displayUrl = rtrim(preg_replace('#^https?://#', '', $url), '/');
			
			return sprintf(
				'<a class="qa-network-site-link" href="%s">
					<div class="qa-network-site-thumbnail">
						<img width="40" height="40" class="qa-lazy-img" data-src="%s" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="/>
					</div>
					<div class="qa-network-site-details">
						<div class="qa-network-site-title" title="%s">%s</div>
						<div class="qa-network-site-description">%s</div>
					</div>
				</a>',
				htmlspecialchars($url),
				htmlspecialchars($iconPath),
				htmlspecialchars($title),
				htmlspecialchars($title),
				htmlspecialchars($displayUrl),
			);
		}
		
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/
