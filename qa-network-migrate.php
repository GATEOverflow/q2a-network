<?php
class qa_network_migrate {
	private $directory;
	private $urltoroot;

	public function load_module($directory, $urltoroot)
	{
		$this->directory = $directory;
		$this->urltoroot = $urltoroot;
	}

	public function suggest_requests()
	{
		return array(
				array(
					'title' => 'Network Migrate',
					'request' => 'network-migrate',
					'nav' => 'footer',
				     ),
			    );
	}

	public function match_request($request)
	{
		// validates the postid so we don't need to do this later
		return ($request == 'network-migrate');
	}



	public function process_request($request)
	{
		$qa_content=qa_content_prepare();
		$qa_content['title']='Network Migrate';
		$user_level = qa_get_logged_in_level();
		if($user_level<QA_USER_LEVEL_MODERATOR)
		{

			if($user_level==null)
				$qa_content['error']="Nothing Yet, Try Logging In, Come back, Ask me again!";
			else
				$qa_content['error']="You Don't Have The Permissions. Ask Me When you Grow Up.";

			return $qa_content;
		}

		require_once QA_INCLUDE_DIR.'qa-app-posts.php';
		$ok = null;


		// get sites

		$idx = 0;
		$sites = array();
		while(qa_opt('network_site_'.$idx.'_url')) {
			$sites[qa_opt('network_site_'.$idx.'_prefix')] = qa_opt('network_site_'.$idx++.'_title');
		}

		if (qa_clicked('network_site_migrate')) {

			$pids =explode(",", qa_post_text('network_site_migrate_id'));
			$prefix = qa_db_escape_string(qa_post_text('network_site_migrate_site'));
			$cat = qa_post_text('network_site_migrate_cat');
			$keep_post_copy = (bool)qa_post_text('keep_post_copy');
			// migrate question to get new id
			foreach($pids as $pid)
			{
				$pid = trim($pid);

				$post = qa_db_read_one_assoc(
						qa_db_query_sub(
							'SELECT * FROM ^posts WHERE postid=#',
							$pid
							),
						true
						);				
				$nid = $this->post_migrate($prefix,$post,null,$cat);

				// migrate children

				$query = qa_db_query_sub(
						'SELECT * FROM ^posts WHERE parentid=#',
						$pid
						);

				$children = 0;

				while(($child = qa_db_read_one_assoc($query,true)) !== null) {

					// migrate child (comment or answer to question)

					$ncid = $this->post_migrate($prefix,$child,$nid);
					$children++;

					if(strpos($child['type'],'A') === 0) {
						// update selchildid if selected

						if($child['postid'] == $post['selchildid']) {
							qa_db_query_sub(
									'UPDATE '.$prefix.'posts SET selchildid=# WHERE postid=#',
									$ncid,$nid
								       );					
						}

						// check for grandchildren

						$query2 = qa_db_query_sub (
								'SELECT * FROM ^posts WHERE parentid=#',
								$child['postid']
								);
						while(($gchild = qa_db_read_one_assoc($query2,true)) !== null) {

							// unrelate related questions... any other choice?
							if(strpos($gchild['type'],'Q') === 0) {
								qa_db_query_sub(
										'UPDATE ^posts SET parentid=NULL WHERE postid=#',
										$gchild['postid']
									       );
							}
							else { // migrate comments to answers
								$this->post_migrate($prefix,$gchild,$ncid);
								$children++;
							}
							//qa_post_delete($gchild['postid']);
						}
						mysqli_free_result($query2);
					}
					if(!$keep_post_copy)
					    qa_post_delete($child['postid']);
				}
				mysqli_free_result($query);

				qa_db_query_sub(
						'INSERT INTO '.$prefix.'postmeta (post_id,meta_key,meta_value) VALUES (#,$,$)',
						$nid,'migrated',QA_MYSQL_TABLE_PREFIX.'|'.time().'|'.qa_get_logged_in_handle()
					       );

				//			qa_post_delete($post['postid']);
if($ok) $ok.="<br>";
				$ok .=  'Post '.$pid.($children?' and '.$children.' child posts':'').' migrated to '.$sites[qa_post_text('network_site_migrate_site')].'.';
			}
		}

		// Create the form for display

		$fields = array();

		$fields[] = array(
				'label' => 'Post IDs to migrate',
				'tags' => 'NAME="network_site_migrate_id"',
				'note' => 'Warning: will migrate all child posts as well.',
				'type' => 'number',
				);

		$fields[] = array(
				'label' => 'Migrate to site:',
				'tags' => 'NAME="network_site_migrate_site"',
				'type' => 'select',
				'options' => $sites,
				);	

		$fields[] = array(
				'label' => 'Category ID on new site',
				'tags' => 'NAME="network_site_migrate_cat"',
				'note' => 'Optional - cat ID must exist on new site',
				'type' => 'number',
				);

		$fields[] = array(
				'label' => 'Keep post copy on current site',
				'tags' => 'NAME="keep_post_copy"',
				'type' => 'checkbox',
				);

		$qa_content['form'] =  array(          
				'tags' => 'method="post" action="'.qa_self_html().'"',

				'style' => 'wide',

				'ok' => ($ok && !isset($error)) ? $ok : null,

				'fields' => $fields,

				'buttons' => array(
					array(
						'label' => 'Migrate Post',
						'tags' => 'NAME="network_site_migrate"',
					     ),
					),
				);
		return $qa_content;
	}

	function post_migrate($prefix,$post,$parentid=null,$cat=null) {
		require_once QA_INCLUDE_DIR.'qa-app-post-update.php';

		// get new parent id

		$result = qa_db_query_sub("SHOW TABLE STATUS LIKE '".$prefix."posts'");
		$row = mysqli_fetch_array($result);
	//	$nid = $row['Auto_increment'];

		// copy post to new site

		qa_db_query_sub(
				'INSERT INTO '.$prefix.'posts (type,parentid,categoryid,catidpath1,catidpath2,catidpath3,acount,amaxvote,selchildid,closedbyid,userid,cookieid,createip,lastuserid,lastip,upvotes,downvotes,netvotes,lastviewip,views,hotness,flagcount,format,created,updated,updatetype,title,content,tags,notify) VALUES($,'.($parentid?qa_db_escape_string($parentid):'NULL').','.($cat?qa_db_escape_string($cat):'NULL').',NULL,NULL,NULL,#,#,#,#,#,#,#,#,#,#,#,#,#,#,#,#,$,#,#,$,$,$,$,$)',
				$post['type'],($post['acount']?$post['acount']:0),($post['amaxvote']?$post['amaxvote']:0),$post['selchildid'],$post['closedbyid'],$post['userid'],$post['cookieid'],$post['createip'],$post['lastuserid'],$post['lastip'],$post['upvotes'],$post['downvotes'],$post['netvotes'],$post['lastviewip'],($post['views']?$post['views']:0),$post['hotness'],$post['flagcount'],($post['format']?$post['format']:''),$post['created'],$post['updated'],$post['updatetype'],$post['title'],$post['content'],$post['tags'],$post['notify']
		);
		
	$nid = mysqli_insert_id(qa_db_connection());	

		mysqli_free_result($result);

		//update answer key 
		$query = qa_db_query_sub(
				'SELECT answer_str,userid,created,edited,editedby FROM ^ec_answers WHERE postid=#',
				$post['postid']
				);
		while(($answer = qa_db_read_one_assoc($query,true)) !== null) {
			qa_db_query_sub(
					'INSERT INTO '.$prefix.'ec_answers (postid,answer_str,userid,created,edited,editedby) VALUES(#,$,#,$,$,#)',
					$nid,$answer['answer_str'],$answer['userid'],$answer['created'],$answer['edited'],$answer['editedby']
				       );	
		}

		// get old uservotes

		$query = qa_db_query_sub(
				'SELECT * FROM ^uservotes WHERE postid=#',
				$post['postid']
				);

		while(($vote = qa_db_read_one_assoc($query,true)) !== null) {
			// add new uservote
			qa_db_query_sub(
					'INSERT INTO '.$prefix.'uservotes (postid,userid,vote,flag,votecreated,voteupdated) VALUES(#,#,#,#,$,$)',
					$nid,$vote['userid'],$vote['vote'],$vote['flag'],$vote['votecreated'],$vote['voteupdated']
				       );	
		}

		mysqli_free_result($query);

		// make remote request for update

		$idx = 0;
		$url = '';
		while($idx <= (int)qa_opt('network_site_number')) {
			if(qa_opt('network_site_'.$idx.'_prefix') == $prefix) {
				$url = qa_opt('network_site_'.$idx.'_url');
				break;
			}
			$idx++;
		}

		// set migrate prefix to invoke override, changing the set of tables temporarily -- yikes!

		global $migrate_change_db;
		$migrate_change_db = $prefix;

		require_once QA_INCLUDE_DIR.'qa-db-post-create.php';
		require_once QA_INCLUDE_DIR.'qa-db-post-update.php';
		require_once QA_INCLUDE_DIR.'qa-db-points.php';
		require_once QA_INCLUDE_DIR.'qa-db-votes.php';

		$post = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^posts WHERE postid=#',
					$nid
					),
				true
				);

		qa_db_posts_calc_category_path($post['postid']);

		$text=qa_post_content_to_text($post['content'], $post['format']);			

		if($post['type'] == 'Q') { 
			$tagstring=qa_post_tags_to_tagstring($post['tags']);

			qa_db_category_path_qcount_update(qa_db_post_get_category_path($post['postid']));
			qa_db_hotness_update($post['postid']);
			qa_post_index($post['postid'], 'Q', $post['postid'], null, $post['title'], $post['content'], $post['format'], $text, $tagstring,($cat?qa_db_escape_string($cat):'NULL'));

			qa_db_points_update_ifuser($post['userid'], array('qposts', 'aselects', 'qvoteds', 'upvoteds', 'downvoteds'));

			qa_db_qcount_update();
			qa_db_unaqcount_update();
			qa_db_unselqcount_update();
			qa_db_unupaqcount_update();

		}
		else if($post['type'] == 'A') { 
			$question = qa_db_read_one_assoc(
					qa_db_query_sub(
						'SELECT * FROM ^posts WHERE postid=#',
						$parentid
						),
					true
					);
			if ($question['type']=='Q')
				qa_post_index($post['postid'], 'A', $question['postid'], $question['postid'], null, $post['content'], $post['format'], $text, null,($cat?qa_db_escape_string($cat):'NULL'));

			qa_db_post_acount_update($question['postid']);
			qa_db_hotness_update($question['postid']);
			qa_db_points_update_ifuser($post['userid'], array('aposts', 'aselecteds', 'avoteds', 'upvoteds', 'downvoteds'));
			qa_db_acount_update();
			qa_db_unaqcount_update();
			qa_db_unupaqcount_update();
		}
		else if($post['type'] == 'C') {
			$parent = qa_db_read_one_assoc(
					qa_db_query_sub(
						'SELECT * FROM ^posts WHERE postid=#',
						$parentid
						),
					true
					);
			if(strpos($parent['type'],'A') === 0)
				$question =  qa_db_read_one_assoc(
						qa_db_query_sub(
							'SELECT * FROM ^posts WHERE postid=#',
							$parent['postid']
							),
						true
						);
			else $question = $parent;

			if ( ($question['type']=='Q') && (($parent['type']=='Q') || ($parent['type']=='A')) ) // only index if antecedents fully visible
				qa_post_index($post['postid'], 'C', $question['postid'], $parent['postid'], null, $post['content'], $post['format'], $text, null,($cat?qa_db_escape_string($cat):'NULL'));

			qa_db_points_update_ifuser($post['userid'], array('cposts'));
			qa_db_ccount_update();
		}

		$migrate_change_db = null;

		return $nid;
	}
}
