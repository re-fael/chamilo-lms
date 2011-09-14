<?php 
/* For licensing terms, see /license.txt */

require_once api_get_path(LIBRARY_PATH).'system_announcements.lib.php';
require_once api_get_path(LIBRARY_PATH).'groupmanager.lib.php';
require_once api_get_path(SYS_CODE_PATH).'survey/survey.lib.php';


class IndexManager {
	var $tpl 	= false; //An instance of the template engine
	var $name 	= '';
	
	var $home			= '';
	var $default_home 	= 'home/';
	
	function __construct($title, $load_template = true) {
		
		if ($load_template) {			
			$this->tpl = new Template($title);					
		}
				
		$home = 'home/';
		if (api_get_multiple_access_url()) {
			$access_url_id = api_get_current_access_url_id();
			$url_info      = api_get_access_url($access_url_id);
			$url           = api_remove_trailing_slash(preg_replace('/https?:\/\//i', '', $url_info['url']));
			$clean_url     = replace_dangerous_char($url);
			$clean_url     = str_replace('/', '-', $clean_url);
			$clean_url     .= '/';			
			// if $clean_url ==  "localhost/" means that the multiple URL was not well configured we don't rename the $home variable
			if ($clean_url != 'localhost/')
			$home          = 'home/'.$clean_url;
		}
		$this->home = $home;
		$this->user_id = api_get_user_id();
		$this->load_directories_preview = true;
	}
	
	
	function set_login_form() {
		global $loginFailed;
		
		$login_form = '';
	
		if (!($this->user_id) || api_is_anonymous($this->user_id)) {
	
			// Only display if the user isn't logged in.
			$this->tpl->assign('login_language_form', api_display_language_form(true));
			$this->tpl->assign('login_form',  self::display_login_form());
			
			if ($loginFailed) {				
				$this->tpl->assign('login_failed',  self::handle_login_failed());
			}
	
			if (api_get_setting('allow_lostpassword') == 'true' || api_get_setting('allow_registration') == 'true') {
				$login_form .= '<ul class="menulist">';
				if (api_get_setting('allow_registration') != 'false') {
					$login_form .= '<li><a href="main/auth/inscription.php">'.get_lang('Reg').'</a></li>';					
				}
				if (api_get_setting('allow_lostpassword') == 'true') {
					$login_form .= '<li><a href="main/auth/lostPassword.php">'.get_lang('LostPassword').'</a></li>';
				}
				$login_form .= '</ul>';
			}
			$this->tpl->assign('login_options',  $login_form);
	
			if (api_number_of_plugins('loginpage_menu') > 0) {
				$login_form = '<div class="note" style="background: none">';
				ob_start();
				api_plugin('loginpage_menu');
				$plugin_login = ob_get_contents();
				$login_form .= $plugin_login;
				$login_form .= '</div>';
				$this->tpl->assign('login_plugin_menu',  $login_form);
			}			
		}
	}
	
	
	function return_exercise_block($personal_course_list) {
		require_once api_get_path(SYS_CODE_PATH).'exercice/exercise.lib.php';
		$exercise_list = array();
		if (!empty($personal_course_list)) {
			foreach($personal_course_list as  $course_item) {
				$course_code 	= $course_item['c'];
				$session_id 	= $course_item['id_session'];
					
				$exercises = get_exercises_to_be_taken($course_code, $session_id);
	
				foreach($exercises as $exercise_item) {
					$exercise_item['course_code'] 	= $course_code;
					$exercise_item['session_id'] 	= $session_id;
					$exercise_item['tms'] 	= api_strtotime($exercise_item['end_time']);
						
					$exercise_list[] = $exercise_item;
				}
			}
			if (!empty($exercise_list)) {
				$exercise_list = msort($exercise_list, 'tms');
				$my_exercise = $exercise_list[0];
				$url = Display::url($my_exercise['title'], api_get_path(WEB_CODE_PATH).'exercice/overview.php?exerciseId='.$my_exercise['id'].'&cidReq='.$my_exercise['course_code'].'&id_session='.$my_exercise['session_id']);
				$this->tpl->assign('exercise_url', $url);
				$this->tpl->assign('exercise_end_date', api_convert_and_format_date($my_exercise['end_time'], DATE_FORMAT_SHORT));
			}
		}
	}
	
	function return_announcements($show_slide = true) {	
		// Display System announcements
		$announcement = isset($_GET['announcement']) ? $_GET['announcement'] : -1;
		$announcement = intval($announcement);
		
		if (isset($_user['user_id'])) {
			$visibility = api_is_allowed_to_create_course() ? VISIBLE_TEACHER : VISIBLE_STUDENT;
			if ($show_slide) {
				$announcements = SystemAnnouncementManager :: display_announcements_slider($visibility, $announcement);
			} else {
				$announcements = SystemAnnouncementManager :: get_all_announcements($visibility, $announcement);
			}
		} else {
			if ($show_slide) {
				$announcements = SystemAnnouncementManager :: display_announcements_slider(VISIBLE_GUEST, $announcement);
			} else {
				$announcements = SystemAnnouncementManager :: get_all_announcements(VISIBLE_GUEST, $announcement);
			}
		}
		return $announcements;
	}
	
	
		
	/**
	 * This function handles the logout and is called whenever there is a $_GET['logout']
	 *
	 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
	 */
	function logout() {
		global $_configuration, $extAuthSource;
		// Variable initialisation.
		$query_string = '';
	
		if (!empty($_SESSION['user_language_choice'])) {
			$query_string = '?language='.$_SESSION['user_language_choice'];
		}
	
		// Database table definition.
		$tbl_track_login = Database :: get_statistic_table(TABLE_STATISTIC_TRACK_E_LOGIN);
	
		// Selecting the last login of the user.
		$uid = $this->user_id;
		
		$sql_last_connection = "SELECT login_id, login_date FROM $tbl_track_login WHERE login_user_id='$uid' ORDER BY login_date DESC LIMIT 0,1";
		$q_last_connection = Database::query($sql_last_connection);
		if (Database::num_rows($q_last_connection) > 0) {
			$i_id_last_connection = Database::result($q_last_connection, 0, 'login_id');
		}
	
		if (!isset($_SESSION['login_as'])) {
			$current_date = date('Y-m-d H:i:s', time());
			$s_sql_update_logout_date = "UPDATE $tbl_track_login SET logout_date='".$current_date."' WHERE login_id='$i_id_last_connection'";
			Database::query($s_sql_update_logout_date);
		}
		LoginDelete($uid); // From inc/lib/online.inc.php - removes the "online" status.
	
		// The following code enables the use of an external logout function.
		// Example: define a $extAuthSource['ldap']['logout'] = 'file.php' in configuration.php.
		// Then a function called ldap_logout() inside that file
		// (using *authent_name*_logout as the function name) and the following code
		// will find and execute it.
		$uinfo = api_get_user_info($uid);
		if (($uinfo['auth_source'] != PLATFORM_AUTH_SOURCE) && is_array($extAuthSource)) {
			if (is_array($extAuthSource[$uinfo['auth_source']])) {
				$subarray = $extAuthSource[$uinfo['auth_source']];
				if (!empty($subarray['logout']) && file_exists($subarray['logout'])) {
					include_once ($subarray['logout']);
					$logout_function = $uinfo['auth_source'].'_logout';
					if (function_exists($logout_function)) {
						$logout_function($uinfo);
					}
				}
			}
		}
		exit_of_chat($uid);
		api_session_destroy();
		header("Location: index.php$query_string");
		exit();
	}
	
	/**
	 * This function checks if there are courses that are open to the world in the platform course categories (=faculties)
	 *
	 * @param unknown_type $category
	 * @return boolean
	 */
	function category_has_open_courses($category) {
		global $setting_show_also_closed_courses;
	
		$user_identified = (api_get_user_id() > 0 && !api_is_anonymous());
		$main_course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
		$sql_query = "SELECT * FROM $main_course_table WHERE category_code='$category'";
		$sql_result = Database::query($sql_query);
		while ($course = Database::fetch_array($sql_result)) {
			if (!$setting_show_also_closed_courses) {
				if ((api_get_user_id() > 0
				&& $course['visibility'] == COURSE_VISIBILITY_OPEN_PLATFORM)
				|| ($course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD)) {
					return true; //at least one open course
				}
			} else {
				if (isset($course['visibility'])) {
					return true; // At least one course (it does not matter weither it's open or not because $setting_show_also_closed_courses = true).
				}
			}
		}
		return false;
	}
	
	
	/**
	 * Displays the right-hand menu for anonymous users:
	 * login form, useful links, help section
	 * Warning: function defines globals
	 * @version 1.0.1
	 * @todo does $_plugins need to be global?
	 */
	function display_anonymous_right_menu() {
		global $loginFailed, $_plugins, $_user, $menu_navigation;
	
		$platformLanguage       	= api_get_setting('platformLanguage');		
		$display_add_course_link	= api_is_allowed_to_create_course() && ($_SESSION['studentview'] != 'studentenview');	
		$current_user_id        	= api_get_user_id();
	
		echo self::set_login_form(false);		
		
		echo self::return_teacher_link();
		
		echo self::return_notice();
		
		//Plugin
		echo self::return_plugin_campushomepage();	
	}
	
	
	
	function return_teacher_link() {
		$html = '';
		if (!empty($this->user_id)) {
			// tabs that are deactivated are added here
		
			$show_menu = false;
			$show_create_link = false;
			$show_course_link = false;
		
			if ($display_add_course_link) {
				$show_menu = true;
				$show_create_link = true;
			}
		
			if (api_is_platform_admin() || api_is_course_admin() || api_is_allowed_to_create_course()) {
				$show_menu = true;
				$show_course_link = true;
			} else {
				if (api_get_setting('allow_students_to_browse_courses') == 'true') {
					$show_menu = true;
					$show_course_link = true;
				}
			}
		
			if ($show_menu && ($show_create_link || $show_course_link )) {
				$show_menu = true;
			} else {
				$show_menu = false;
			}
		}	
		
		// My Account section
		
		if ($show_menu) {
			$html .= '<ul class="menulist">';
			if ($show_create_link) {			
				$html .= '<li><a href="main/create_course/add_course.php">'.(api_get_setting('course_validation') == 'true' ? get_lang('CreateCourseRequest') : get_lang('CourseCreate')).'</a></li>';
			}
			
			if ($show_course_link) {
				if (!api_is_drh() && !api_is_session_admin()) {
					$html .=  '<li><a href="main/auth/courses.php">'.get_lang('CourseManagement').'</a></li>';					
				} else {
					$html .= '<li><a href="main/dashboard/index.php">'.get_lang('Dashboard').'</a></li>';
				}
			}		
			$html .= '</ul>';
		}	
		
		if (!empty($html)) {
			$html = self::show_right_block(get_lang('MenuUser'), $html);
		}
		return $html;
	}
	
	
	function return_home_page() {	

		// Including the page for the news
		$html = '';
		
		if (!empty($_GET['include']) && preg_match('/^[a-zA-Z0-9_-]*\.html$/', $_GET['include'])) {
			$open = @(string)file_get_contents(api_get_path(SYS_PATH).$this->home.$_GET['include']);
			$html = api_to_system_encoding($open, api_detect_encoding(strip_tags($open)));		 			
		} else {		
			if (!empty($_SESSION['user_language_choice'])) {
				$user_selected_language = $_SESSION['user_language_choice'];
			} elseif (!empty($_SESSION['_user']['language'])) {
				$user_selected_language = $_SESSION['_user']['language'];
			} else {
				$user_selected_language = api_get_setting('platformLanguage');
			}		
			if (!file_exists($this->home.'home_news_'.$user_selected_language.'.html')) {
				if (file_exists($this->home.'home_top.html')) {
					$home_top_temp = file($this->home.'home_top.html');
				} else {
					$home_top_temp = file($this->default_home.'home_top.html');
				}
				$home_top_temp = implode('', $home_top_temp);
			} else {
				if (file_exists($this->home.'home_top_'.$user_selected_language.'.html')) {
					$home_top_temp = file_get_contents($this->home.'home_top_'.$user_selected_language.'.html');
				} else {
					$home_top_temp = file_get_contents($this->home.'home_top.html');
				}
			}
			if (trim($home_top_temp) == '' && api_is_platform_admin()) {
				$home_top_temp = get_lang('PortalHomepageDefaultIntroduction');
			}
			$open = str_replace('{rel_path}', api_get_path(REL_PATH), $home_top_temp);
			$html = api_to_system_encoding($open, api_detect_encoding(strip_tags($open)));				
		}
		return $html;		
	}
	
	function return_notice() {
		$sys_path               = api_get_path(SYS_PATH);
		$user_selected_language = api_get_interface_language();
		
		$html = '';
		// Notice
		$home_notice = @(string)file_get_contents($sys_path.$this->home.'home_notice_'.$user_selected_language.'.html');
		if (empty($home_notice)) {
			$home_notice = @(string)file_get_contents($sys_path.$this->home.'home_notice.html');
		}
		
		if (!empty($home_notice)) {
			$home_notice = api_to_system_encoding($home_notice, api_detect_encoding(strip_tags($home_notice)));
			echo self::show_right_block('', $home_notice, 'note');
		}
		
		if (isset($_SESSION['_user']['user_id']) && $_SESSION['_user']['user_id'] != 0) {
			// Deleting the myprofile link.
			if (api_get_setting('allow_social_tool') == 'true') {
				unset($menu_navigation['myprofile']);
			}
		
			if (!empty($menu_navigation)) {
				$content = '<ul class="menulist">';
				foreach ($menu_navigation as $section => $navigation_info) {
					$current = $section == $GLOBALS['this_section'] ? ' id="current"' : '';
					$content .='<li'.$current.'><a href="'.$navigation_info['url'].'" target="_self">'.$navigation_info['title'].'</a></li>';
				}
				$content .= '</ul>';
				$html .= self::show_right_block(get_lang('MainNavigation'), $content);
			}
		}
		
		// Help section.
		/* Hide right menu "general" and other parts on anonymous right menu. */
		
		if (!isset($user_selected_language)) {
			$user_selected_language = $platformLanguage;
		}
		
		$home_menu = @(string)file_get_contents($sys_path.$this->home.'home_menu_'.$user_selected_language.'.html');
		if (!empty($home_menu)) {
			$home_menu_content .= '<ul class="menulist">';
			$home_menu_content .= api_to_system_encoding($home_menu, api_detect_encoding(strip_tags($home_menu)));
			$home_menu_content .= '</ul>';
			$html .= self::show_right_block(get_lang('MenuGeneral'), $home_menu_content);
		}
		
		return $html;
	}
	
	function return_plugin_campushomepage() {
		$html = '';
		if (api_get_user_id() && api_number_of_plugins('campushomepage_menu') > 0) {
			ob_start();
			api_plugin('campushomepage_menu');
			$plugin_content = ob_get_contents();
			ob_end_clean();
			$html = self::show_right_block('', $plugin_content);
		}
		return $html;
	}
	
	/**
	 * Reacts on a failed login:
	 * Displays an explanation with a link to the registration form.
	 *
	 * @version 1.0.1
	 */
	function handle_login_failed() {
		if (!isset($_GET['error'])) {
			$message = get_lang('InvalidId');
			if (api_is_self_registration_allowed()) {
				$message = get_lang('InvalidForSelfRegistration');
			}
		} else {
			switch ($_GET['error']) {
				case '':
					$message = get_lang('InvalidId');
					if (api_is_self_registration_allowed()) {
						$message = get_lang('InvalidForSelfRegistration');
					}
					break;
				case 'account_expired':
					$message = get_lang('AccountExpired');
					break;
				case 'account_inactive':
					$message = get_lang('AccountInactive');
					break;
				case 'user_password_incorrect':
					$message = get_lang('InvalidId');
					break;
				case 'access_url_inactive':
					$message = get_lang('AccountURLInactive');
					break;
			}
		}
		return '<div id="login_fail">'.$message.'</div>';
	}
	
	
	
	
	/**
	 * Display list of courses in a category.
	 * (for anonymous users)
	 *
	 * @version 1.1
	 * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University - refactoring and code cleaning
	 */
	function display_anonymous_course_list() {
		$ctok = $_SESSION['sec_token'];
		$stok = Security::get_token();
	
		// Initialization.
		$user_identified = (api_get_user_id() > 0 && !api_is_anonymous());
		$web_course_path = api_get_path(WEB_COURSE_PATH);
		$category = Database::escape_string($_GET['category']);
		global $setting_show_also_closed_courses;
	
		// Database table definitions.
		$main_course_table      = Database :: get_main_table(TABLE_MAIN_COURSE);
		$main_category_table    = Database :: get_main_table(TABLE_MAIN_CATEGORY);
	
		$platformLanguage = api_get_setting('platformLanguage');
	
		// Get list of courses in category $category.
		$sql_get_course_list = "SELECT * FROM $main_course_table cours
	                                WHERE category_code = '".Database::escape_string($_GET['category'])."'
	                                ORDER BY title, UPPER(visual_code)";
	
		// Showing only the courses of the current access_url_id.
		global $_configuration;
		if ($_configuration['multiple_access_urls']) {
			$url_access_id = api_get_current_access_url_id();
			if ($url_access_id != -1) {
				$tbl_url_rel_course = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
				$sql_get_course_list = "SELECT * FROM $main_course_table as course INNER JOIN $tbl_url_rel_course as url_rel_course
	                    ON (url_rel_course.course_code=course.code)
	                    WHERE access_url_id = $url_access_id AND category_code = '".Database::escape_string($_GET['category'])."' ORDER BY title, UPPER(visual_code)";
			}
		}
	
		// Removed: AND cours.visibility='".COURSE_VISIBILITY_OPEN_WORLD."'
		$sql_result_courses = Database::query($sql_get_course_list);
	
		while ($course_result = Database::fetch_array($sql_result_courses)) {
			$course_list[] = $course_result;
		}
	
		$platform_visible_courses = '';
		// $setting_show_also_closed_courses
		if ($user_identified) {
			if ($setting_show_also_closed_courses) {
				$platform_visible_courses = '';
			} else {
				$platform_visible_courses = "  AND (t3.visibility='".COURSE_VISIBILITY_OPEN_WORLD."' OR t3.visibility='".COURSE_VISIBILITY_OPEN_PLATFORM."' )";
			}
		} else {
			if ($setting_show_also_closed_courses) {
				$platform_visible_courses = '';
			} else {
				$platform_visible_courses = "  AND (t3.visibility='".COURSE_VISIBILITY_OPEN_WORLD."' )";
			}
		}
		$sqlGetSubCatList = "
	                SELECT t1.name,t1.code,t1.parent_id,t1.children_count,COUNT(DISTINCT t3.code) AS nbCourse
	                FROM $main_category_table t1
	                LEFT JOIN $main_category_table t2 ON t1.code=t2.parent_id
	                LEFT JOIN $main_course_table t3 ON (t3.category_code=t1.code $platform_visible_courses)
	                WHERE t1.parent_id ". (empty ($category) ? "IS NULL" : "='$category'")."
	                GROUP BY t1.name,t1.code,t1.parent_id,t1.children_count ORDER BY t1.tree_pos, t1.name";
	
	
		// Showing only the category of courses of the current access_url_id.
		global $_configuration;
		if ($_configuration['multiple_access_urls']) {
			$url_access_id = api_get_current_access_url_id();
			if ($url_access_id != -1) {
				$tbl_url_rel_course = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
				$sqlGetSubCatList = "
	                SELECT t1.name,t1.code,t1.parent_id,t1.children_count,COUNT(DISTINCT t3.code) AS nbCourse
	                FROM $main_category_table t1
	                LEFT JOIN $main_category_table t2 ON t1.code=t2.parent_id
	                LEFT JOIN $main_course_table t3 ON (t3.category_code=t1.code $platform_visible_courses)
	                INNER JOIN $tbl_url_rel_course as url_rel_course
	                    ON (url_rel_course.course_code=t3.code)
	                WHERE access_url_id = $url_access_id AND t1.parent_id ".(empty($category) ? "IS NULL" : "='$category'")."
	                GROUP BY t1.name,t1.code,t1.parent_id,t1.children_count ORDER BY t1.tree_pos, t1.name";
			}
		}
	
		$resCats = Database::query($sqlGetSubCatList);
		$thereIsSubCat = false;
		if (Database::num_rows($resCats) > 0) {
			$htmlListCat = '<h4 style="margin-top: 0px;">'.get_lang('CatList').'</h4><ul>';
			while ($catLine = Database::fetch_array($resCats)) {
				if ($catLine['code'] != $category) {
	
					$category_has_open_courses = self::category_has_open_courses($catLine['code']);
					if ($category_has_open_courses) {
						// The category contains courses accessible to anonymous visitors.
						$htmlListCat .= '<li>';
						$htmlListCat .= '<a href="'.api_get_self().'?category='.$catLine['code'].'">'.$catLine['name'].'</a>';
						if (api_get_setting('show_number_of_courses') == 'true') {
							$htmlListCat .= ' ('.$catLine['nbCourse'].' '.get_lang('Courses').')';
						}
						$htmlListCat .= "</li>\n";
						$thereIsSubCat = true;
					} elseif ($catLine['children_count'] > 0) {
						// The category has children, subcategories.
						$htmlListCat .= '<li>';
						$htmlListCat .= '<a href="'.api_get_self().'?category='.$catLine['code'].'">'.$catLine['name'].'</a>';
						$htmlListCat .= "</li>\n";
						$thereIsSubCat = true;
					}
					/* End changed code to eliminate the (0 courses) after empty categories. */
					elseif (api_get_setting('show_empty_course_categories') == 'true') {
						$htmlListCat .= '<li>';
						$htmlListCat .= $catLine['name'];
						$htmlListCat .= "</li>\n";
						$thereIsSubCat = true;
					} // Else don't set thereIsSubCat to true to avoid printing things if not requested.
				} else {
					$htmlTitre = '<p>';
					if (api_get_setting('show_back_link_on_top_of_tree') == 'true') {
						$htmlTitre .= '<a href="'.api_get_self().'">&lt;&lt; '.get_lang('BackToHomePage').'</a>';
					}
					if (!is_null($catLine['parent_id']) || (api_get_setting('show_back_link_on_top_of_tree') != 'true' && !is_null($catLine['code']))) {
						$htmlTitre .= '<a href="'.api_get_self().'?category='.$catLine['parent_id'].'">&lt;&lt; '.get_lang('Up').'</a>';
					}
					$htmlTitre .= "</p>\n";
					if ($category != "" && !is_null($catLine['code'])) {
						$htmlTitre .= '<h3>'.$catLine['name']."</h3>\n";
					} else {
						$htmlTitre .= '<h3>'.get_lang('Categories')."</h3>\n";
					}
				}
			}
			$htmlListCat .= "</ul>";
		}
		echo $htmlTitre;
		if ($thereIsSubCat) {
			echo $htmlListCat;
		}
		while ($categoryName = Database::fetch_array($resCats)) {
			echo '<h3>', $categoryName['name'], "</h3>\n";
		}
		$numrows = Database::num_rows($sql_result_courses);
		$courses_list_string = '';
		$courses_shown = 0;
		if ($numrows > 0) {
			if ($thereIsSubCat) {
				$courses_list_string .= "<hr size=\"1\" noshade=\"noshade\">\n";
			}
			$courses_list_string .= '<h4 style="margin-top: 0px;">'.get_lang('CourseList')."</h4>\n<ul>\n";
	
			if (api_get_user_id()) {
				$courses_of_user = self::get_courses_of_user(api_get_user_id());
			}
	
			foreach ($course_list as $course) {
				// $setting_show_also_closed_courses
				if (!$setting_show_also_closed_courses) {
					// If we do not show the closed courses
					// we only show the courses that are open to the world (to everybody)
					// and the courses that are open to the platform (if the current user is a registered user.
					if( ($user_identified && $course['visibility'] == COURSE_VISIBILITY_OPEN_PLATFORM) || ($course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD)) {
						$courses_shown++;
						$courses_list_string .= "<li>\n";
						$courses_list_string .= '<a href="'.$web_course_path.$course['directory'].'/">'.$course['title'].'</a><br />';
						if (api_get_setting('display_coursecode_in_courselist') == 'true') {
							$courses_list_string .= $course['visual_code'];
						}
						if (api_get_setting('display_coursecode_in_courselist') == 'true' && api_get_setting('display_teacher_in_courselist') == 'true') {
							$courses_list_string .= ' - ';
						}
						if (api_get_setting('display_teacher_in_courselist') == 'true') {
							$courses_list_string .= $course['tutor_name'];
						}
						if (api_get_setting('show_different_course_language') == 'true' && $course['course_language'] != api_get_setting('platformLanguage')) {
							$courses_list_string .= ' - '.$course['course_language'];
						}
						$courses_list_string .= "</li>\n";
					}
				}
				// We DO show the closed courses.
				// The course is accessible if (link to the course homepage):
				// 1. the course is open to the world (doesn't matter if the user is logged in or not): $course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD);
				// 2. the user is logged in and the course is open to the world or open to the platform: ($user_identified && $course['visibility'] == COURSE_VISIBILITY_OPEN_PLATFORM);
				// 3. the user is logged in and the user is subscribed to the course and the course visibility is not COURSE_VISIBILITY_CLOSED;
				// 4. the user is logged in and the user is course admin of te course (regardless of the course visibility setting);
				// 5. the user is the platform admin api_is_platform_admin().
				//
				else {
				$courses_shown++;
					$courses_list_string .= "<li>\n";
					if ($course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD
					|| ($user_identified && $course['visibility'] == COURSE_VISIBILITY_OPEN_PLATFORM)
					|| ($user_identified && key_exists($course['code'], $courses_of_user) && $course['visibility'] != COURSE_VISIBILITY_CLOSED)
					|| $courses_of_user[$course['code']]['status'] == '1'
					|| api_is_platform_admin()) {
					$courses_list_string .= '<a href="'.$web_course_path.$course['directory'].'/">';
	                }
					$courses_list_string .= $course['title'];
						if ($course['visibility'] == COURSE_VISIBILITY_OPEN_WORLD
						|| ($user_identified && $course['visibility'] == COURSE_VISIBILITY_OPEN_PLATFORM)
						|| ($user_identified && key_exists($course['code'], $courses_of_user) && $course['visibility'] != COURSE_VISIBILITY_CLOSED)
	                        || $courses_of_user[$course['code']]['status'] == '1'
						|| api_is_platform_admin()) {
	                    $courses_list_string .= '</a><br />';
				}
						if (api_get_setting('display_coursecode_in_courselist') == 'true') {
						$courses_list_string .= ' '.$course['visual_code'];
				}
						if (api_get_setting('display_coursecode_in_courselist') == 'true' && api_get_setting('display_teacher_in_courselist') == 'true') {
	                    $courses_list_string .= ' - ';
				}
						if (api_get_setting('display_teacher_in_courselist') == 'true') {
						$courses_list_string .= $course['tutor_name'];
	                }
	                if (api_get_setting('show_different_course_language') == 'true' && $course['course_language'] != api_get_setting('platformLanguage')) {
						$courses_list_string .= ' - '.$course['course_language'];
	                }
						if (api_get_setting('show_different_course_language') == 'true' && $course['course_language'] != api_get_setting('platformLanguage')) {
	                    $courses_list_string .= ' - '.$course['course_language'];
	                }
						// We display a subscription link if:
	                // 1. it is allowed to register for the course and if the course is not already in the courselist of the user and if the user is identiefied
	                // 2.
						if ($user_identified && !key_exists($course['code'], $courses_of_user)) {
		                    if ($course['subscribe'] == '1') {
							$courses_list_string .= '<form action="main/auth/courses.php?action=subscribe&category='.Security::remove_XSS($_GET['category']).'" method="post">';
							$courses_list_string .= '<input type="hidden" name="sec_token" value="'.$stok.'">';
							$courses_list_string .= '<input type="hidden" name="subscribe" value="'.$course['code'].'" />';
		                        $courses_list_string .= '<input type="image" name="unsub" src="main/img/enroll.gif" alt="'.get_lang('Subscribe').'" />'.get_lang('Subscribe').'</form>';
		                    } else {
	                        	$courses_list_string .= '<br />'.get_lang('SubscribingNotAllowed');
	                        }
						}
						$courses_list_string .= "</li>";
	            }
	        }
	        $courses_list_string .= "</ul>";
						} else {
						//echo '<blockquote>', get_lang('_No_course_publicly_available'), "</blockquote>\n";
	   					}
					if ($courses_shown > 0) {
						// Only display the list of courses and categories if there was more than
                                // 0 courses visible to the world (we're in the anonymous list here).
							echo $courses_list_string;
					}
		if ($category != '') {
			echo '<p><a href="'.api_get_self().'"> ', Display :: return_icon('back.png', get_lang('BackToHomePage')), get_lang('BackToHomePage'), '</a></p>';
		}
	}
	
	/**
	* retrieves all the courses that the user has already subscribed to
		* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, Belgium
	* @param int $user_id: the id of the user
	* @return array an array containing all the information of the courses of the given user
		*/
	function get_courses_of_user($user_id) {
			$table_course       = Database::get_main_table(TABLE_MAIN_COURSE);
	    	$table_course_user  = Database::get_main_table(TABLE_MAIN_COURSE_USER);
			// Secondly we select the courses that are in a category (user_course_cat <> 0) and sort these according to the sort of the category
			$user_id = intval($user_id);
			$sql_select_courses = "SELECT course.code k, course.visual_code  vc, course.subscribe subscr, course.unsubscribe unsubscr,
			course.title i, course.tutor_name t, course.db_name db, course.directory dir, course_rel_user.status status,
			course_rel_user.sort sort, course_rel_user.user_course_cat user_course_cat
			FROM    $table_course       course,
			$table_course_user  course_rel_user
			WHERE course.code = course_rel_user.course_code
			AND   course_rel_user.user_id = '".$user_id."'
	                                AND course_rel_user.relation_type<>".COURSE_RELATION_TYPE_RRHH."
	                                ORDER BY course_rel_user.sort ASC";
	    $result = Database::query($sql_select_courses);
	    $courses = array();
	    while ($row = Database::fetch_array($result)) {
	        // We only need the database name of the course.
	        $courses[$row['k']] = array('db' => $row['db'], 'code' => $row['k'], 'visual_code' => $row['vc'], 'title' => $row['i'], 'directory' => $row['dir'], 'status' => $row['status'], 'tutor' => $row['t'], 'subscribe' => $row['subscr'], 'unsubscribe' => $row['unsubscr'], 'sort' => $row['sort'], 'user_course_category' => $row['user_course_cat']);
	    }
		return $courses;
	}
	
	
	function show_right_block($title, $content, $class = '') {
	    $html = '';  
		$html.= '<div id="menu" class="menu">';
		$html.= '<div class="menusection '.$class.' ">';
		if (!empty($title)) {
			$html.= '<span class="menusectioncaption">'.$title.'</span>';
		}
		$html.= $content;
	    $html.= '</div>';        
	    $html.= '</div>';   
		return $html;
	}
	
	/**
	 * Adds a form to let users login
	 * @version 1.1
	 */
	function display_login_form() {
		$form = new FormValidator('formLogin');
		$form->addElement('text', 'login', get_lang('UserName'), array('size' => 17));
		$form->addElement('password', 'password', get_lang('Pass'), array('size' => 17));
		$form->addElement('style_submit_button','submitAuth', get_lang('LoginEnter'), array('class' => 'login'));
		$renderer =& $form->defaultRenderer();
		$renderer->setElementTemplate('<div><label>{label}</label></div><div>{element}</div>');
		$html = $form->return_form();
		if (api_get_setting('openid_authentication') == 'true') {
			include_once 'main/auth/openid/login.php';
			$html .= '<div>'.openid_form().'</div>';
		}
		return $html;
	}
	

	
	function return_search_block() {
		$html = '';
		
		if (api_get_setting('search_enabled') == 'true') {
			$html .= '<div class="searchbox">';
			$search_btn = get_lang('Search');
			$search_text_default = get_lang('YourTextHere');
			$search_content = '<br />
		    	<form action="main/search/" method="post">
		    	<input type="text" id="query" size="15" name="query" value="" />
		    	<button class="save" type="submit" name="submit" value="'.$search_btn.'" />'.$search_btn.' </button>
		    	</form></div>';    
			$html .= self::show_right_block(get_lang('Search'), $search_content);
		}
		return $html;	
	}
	
	function return_classes_block() {
		$html = '';
		if (api_get_setting('show_groups_to_users') == 'true') {
			require_once api_get_path(LIBRARY_PATH).'usergroup.lib.php';
			$usergroup = new Usergroup();
			$usergroup_list = $usergroup->get_usergroup_by_user(api_get_user_id());
			$classes = '';
			if (!empty($usergroup_list)) {
				foreach($usergroup_list as $group_id) {
					$data = $usergroup->get($group_id);
					$data['name'] = Display::url($data['name'], api_get_path(WEB_CODE_PATH).'user/classes.php?id='.$data['id']);
					$classes .= Display::tag('li', $data['name']);
				}
			}
			if (api_is_platform_admin()) {
				$classes .= Display::tag('li',  Display::url(get_lang('AddClasses') ,api_get_path(WEB_CODE_PATH).'admin/usergroups.php?action=add'));
			}
			if (!empty($classes)) {
				$classes = Display::tag('ul', $classes, array('class'=>'menulist'));
				$html .= self::show_right_block(get_lang('Classes'), $classes);
			}		
		}
		return $html;
	}
	
	function return_reservation_block() {
		$html = '';
		if (api_get_setting('allow_reservation') == 'true' && api_is_allowed_to_create_course()) {
			$booking_content .='<ul class="menulist">';
			$booking_content .='<a href="main/reservation/reservation.php">'.get_lang('ManageReservations').'</a><br />';
			$booking_content .='</ul>';
			$html .= self::show_right_block(get_lang('Booking'), $booking_content);
		}
		return $html;
	}
	
	function return_plugin_courses_block() {
		global $_plugins;
		// Plugins for the my courses menu.
		if (isset($_plugins['mycourses_menu']) && is_array($_plugins['mycourses_menu'])) {
			ob_start();
			api_plugin('mycourses_menu');
			$plugin_content = ob_get_contents();
			ob_end_clean();
			echo self::show_right_block('', $plugin_content);
		}
	}
	
	function return_profile_block() {
		$html = '';
		$user_id = api_get_user_id();
		if (empty($user_id)) {
			return; 
		}
		
		//Always show the user image
		$img_array = UserManager::get_user_picture_path_by_id(api_get_user_id(), 'web', true, true);
		$no_image = false;
		if ($img_array['file'] == 'unknown.jpg') {
			$no_image = true;
		}
		$img_array = UserManager::get_picture_user(api_get_user_id(), $img_array['file'], 50, USER_IMAGE_SIZE_MEDIUM, ' width="90" height="90" ');
		
		$profile_content = '<div id="social_widget">';
		
		$profile_content .= '<div id="social_widget_image">';
		if (api_get_setting('allow_social_tool') == 'true') {
			if (!$no_image) {
				$profile_content .='<a href="'.api_get_path(WEB_PATH).'main/social/home.php"><img src="'.$img_array['file'].'"  '.$img_array['style'].' border="1"></a>';
			} else {
				$profile_content .='<a href="'.api_get_path(WEB_PATH).'main/auth/profile.php"><img title="'.get_lang('EditProfile').'" src="'.$img_array['file'].'" '.$img_array['style'].' border="1"></a>';
			}
		} else {
			$profile_content .='<a href="'.api_get_path(WEB_PATH).'main/auth/profile.php"><img title="'.get_lang('EditProfile').'" src="'.$img_array['file'].'" '.$img_array['style'].' border="1"></a>';
		}
		$profile_content .= ' </div></div>';
		
		//  @todo Add a platform setting to add the user image.
		if (api_get_setting('allow_message_tool') == 'true') {
			require_once api_get_path(LIBRARY_PATH).'group_portal_manager.lib.php';
		
			// New messages.
			$number_of_new_messages             = MessageManager::get_new_messages();
			// New contact invitations.
			$number_of_new_messages_of_friend   = SocialManager::get_message_number_invitation_by_user_id(api_get_user_id());
		
			// New group invitations sent by a moderator.
			$group_pending_invitations = GroupPortalManager::get_groups_by_user(api_get_user_id(), GROUP_USER_PERMISSION_PENDING_INVITATION, false);
			$group_pending_invitations = count($group_pending_invitations);
		
			$total_invitations = $number_of_new_messages_of_friend + $group_pending_invitations;
			$cant_msg  = '';
			if ($number_of_new_messages > 0) {
				$cant_msg = ' ('.$number_of_new_messages.')';
			}
			$profile_content .= '<div class="clear"></div>';
			$profile_content .= '<div class="message-content"><ul class="menulist">';
			$link = '';
			if (api_get_setting('allow_social_tool') == 'true') {
				$link = '?f=social';
			}
			$profile_content .= '<li><a href="'.api_get_path(WEB_PATH).'main/messages/inbox.php'.$link.'" class="message-body">'.get_lang('Inbox').$cant_msg.' </a></li>';
			$profile_content .= '<li><a href="'.api_get_path(WEB_PATH).'main/messages/new_message.php'.$link.'" class="message-body">'.get_lang('Compose').' </a></li>';
		
			if (api_get_setting('allow_social_tool') == 'true') {
				if ($total_invitations == 0) {
					$total_invitations = '';
				} else {
					$total_invitations = ' ('.$total_invitations.')';
				}
				$profile_content .= '<li><a href="'.api_get_path(WEB_PATH).'main/social/invitations.php" class="message-body">'.get_lang('PendingInvitations').' '.$total_invitations.' </a></li>';
			}
			$profile_content .= '</ul>';
			$profile_content .= '</div>';
		}
		$html = self::show_right_block(get_lang('Profile'), $profile_content);
		return $html;
	}
	
	function return_navigation_course_links($menu_navigation) {
		$html = '';
		
		// Deleting the myprofile link.
		if (api_get_setting('allow_social_tool') == 'true') {
			unset($menu_navigation['myprofile']);
		}
		
		// Main navigation section.
		// Tabs that are deactivated are added here.
		if (!empty($menu_navigation)) {
			$main_navigation_content .= '<ul class="menulist">';
		
			foreach ($menu_navigation as $section => $navigation_info) {
				$current = $section == $GLOBALS['this_section'] ? ' id="current"' : '';
				$main_navigation_content .= '<li'.$current.'>';
				$main_navigation_content .= '<a href="'.$navigation_info['url'].'" target="_self">'.$navigation_info['title'].'</a>';
				$main_navigation_content .= '</li>';
			}
			$main_navigation_content .= '</ul>';
			$html = self::show_right_block(get_lang('MainNavigation'), $main_navigation_content);
		}
		return $html;
	}
	
	function return_account_block() {
		$html = '';
		
		$show_menu        = false;
		$show_create_link = false;
		$show_course_link = false;
		$show_digest_link = false;
		
		$display_add_course_link = api_is_allowed_to_create_course() && ($_SESSION['studentview'] != 'studentenview');
		if ($display_add_course_link) {
			$show_menu = true;
			$show_create_link = true;
		}
		
		if (api_is_platform_admin() || api_is_course_admin() || api_is_allowed_to_create_course()) {
			$show_menu = true;
			$show_course_link = true;
		} else {
			if (api_get_setting('allow_students_to_browse_courses') == 'true') {
				$show_menu = true;
				$show_course_link = true;
			}
		}
		
		if (isset($toolsList) && is_array($toolsList) && isset($digest)) {
			$show_digest_link = true;
			$show_menu = true;
		}
	
		
		// My account section.
		if ($show_menu) {
			$my_account_content = '<ul class="menulist">';
			if ($show_create_link) {
				$my_account_content .= '<li><a href="main/create_course/add_course.php">'.(api_get_setting('course_validation') == 'true' ? get_lang('CreateCourseRequest') : get_lang('CourseCreate')).'</a></li>';
			}
			if ($show_course_link) {
				if (!api_is_drh()) {
					$my_account_content .=  '<li><a href="main/auth/courses.php">'.get_lang('CourseManagement').'</a></li>';
		
					$url = api_get_path(WEB_CODE_PATH).'auth/courses.php?action=sortmycourses';
					$my_account_content .=  Display::url(get_lang('SortMyCourses'), $url);
		
					if (api_get_setting('use_session_mode') == 'true') {
						if (isset($_GET['history']) && intval($_GET['history']) == 1) {
							$my_account_content .=  '<li><a href="user_portal.php">'.get_lang('DisplayTrainingList').'</a></li>';
						} else {
							$my_account_content .=  '<li><a href="user_portal.php?history=1">'.get_lang('HistoryTrainingSessions').'</a></li>';
						}
					}
				} else {
					$my_account_content .=  '<li><a href="main/dashboard/index.php">'.get_lang('Dashboard').'</a></li>';
				}
			}
			if ($show_digest_link) {
				//digest never used?
				//$my_account_content .= Display :: display_digest($toolsList, $digest, $orderKey, $courses);
			}
			$my_account_content .= '</ul>';
		}
		
		if (!empty($my_account_content)) {
			$html =  self::show_right_block(get_lang('MenuUser'), $my_account_content);
		}
		return $html;
	}
	
	function return_courses_main_plugin() {
		ob_start();
		echo '<div id="plugin-mycourses_main">';
		api_plugin('mycourses_main');
		echo '</div>';
		$plugin_content = ob_get_contents();
		ob_end_clean();
		return $plugin_content;
	}
	
	/**
	 * The most important function here, prints the session and course list
	 *  
	 * */
	function return_courses_and_sessions($personal_course_list) {
		
		// Don't change these settings
		define('SCRIPTVAL_No', 0);
		define('SCRIPTVAL_InCourseList', 1);
		define('SCRIPTVAL_UnderCourseList', 2);
		define('SCRIPTVAL_Both', 3);
		define('SCRIPTVAL_NewEntriesOfTheDay', 4);
		define('SCRIPTVAL_NewEntriesOfTheDayOfLastLogin', 5);
		define('SCRIPTVAL_NoTimeLimit', 6);
		// End 'don't change' section	
		
		// ---- Course list options ----
		define('CONFVAL_showCourseLangIfNotSameThatPlatform', true);
		// Preview of course content
		// to disable all: set CONFVAL_maxTotalByCourse = 0
		// to enable all: set e.g. CONFVAL_maxTotalByCourse = 5
		// by default disabled since what's new icons are better (see function display_digest() )
		define('CONFVAL_maxValvasByCourse', 2); // Maximum number of entries
		define('CONFVAL_maxAgendaByCourse', 2); // collected from each course
		define('CONFVAL_maxTotalByCourse', 0); //  and displayed in summary.
		define('CONFVAL_NB_CHAR_FROM_CONTENT', 80);
		// Order to sort data
		$orderKey = array('keyTools', 'keyTime', 'keyCourse'); // default "best" Choice
		//$orderKey = array('keyTools', 'keyCourse', 'keyTime');
		//$orderKey = array('keyCourse', 'keyTime', 'keyTools');
		//$orderKey = array('keyCourse', 'keyTools', 'keyTime');
		define('CONFVAL_showExtractInfo', SCRIPTVAL_UnderCourseList);
		// SCRIPTVAL_InCourseList        // best choice if $orderKey[0] == 'keyCourse'
		// SCRIPTVAL_UnderCourseList    // best choice
		// SCRIPTVAL_Both // probably only for debug
		//define('CONFVAL_dateFormatForInfosFromCourses', get_lang('dateFormatShort'));
		define('CONFVAL_dateFormatForInfosFromCourses', get_lang('dateFormatLong'));
		//define("CONFVAL_limitPreviewTo",SCRIPTVAL_NewEntriesOfTheDay);
		//define("CONFVAL_limitPreviewTo",SCRIPTVAL_NoTimeLimit);
		define("CONFVAL_limitPreviewTo", SCRIPTVAL_NewEntriesOfTheDayOfLastLogin);
		
		
		if (isset($_GET['history']) && intval($_GET['history']) == 1) {
			echo Display::tag('h2', get_lang('HistoryTrainingSession'));
			//if (empty($courses_tree[0]['sessions'])){
			if (empty($courses_tree)) {
				echo get_lang('YouDoNotHaveAnySessionInItsHistory');
			}
		}		
		
		/* PERSONAL COURSE LIST */
		
		if (!isset ($maxValvas)) {
			$maxValvas = CONFVAL_maxValvasByCourse; // Maximum number of entries
		}
		if (!isset ($maxAgenda)) {
			$maxAgenda = CONFVAL_maxAgendaByCourse; // collected from each course
		}
		if (!isset ($maxCourse)) {
			$maxCourse = CONFVAL_maxTotalByCourse; // and displayed in summary.
		}
		
		$maxValvas = (int) $maxValvas;
		$maxAgenda = (int) $maxAgenda;
		$maxCourse = (int) $maxCourse; // 0 if invalid.
		
		/* DISPLAY COURSES */
		
		// Compose a structured array of session categories, sessions and courses
		// for the current user.
		
		if (isset($_GET['history']) && intval($_GET['history']) == 1) {
			$courses_tree = UserManager::get_sessions_by_category(api_get_user_id(), true, true, true);
			if (empty($courses_tree[0]) && count($courses_tree) == 1) {
				$courses_tree = null;
			}
		} else {
			$courses_tree = UserManager::get_sessions_by_category(api_get_user_id(), true, false, true);
		}
		
		if (!empty($courses_tree)) {
			foreach ($courses_tree as $cat => $sessions) {
				$courses_tree[$cat]['details'] = SessionManager::get_session_category($cat);
				if ($cat == 0) {
					$courses_tree[$cat]['courses'] = CourseManager::get_courses_list_by_user_id(api_get_user_id(), false);
				}
				$courses_tree[$cat]['sessions'] = array_flip(array_flip($sessions));
				if (count($courses_tree[$cat]['sessions']) > 0) {
					foreach ($courses_tree[$cat]['sessions'] as $k => $s_id) {
						$courses_tree[$cat]['sessions'][$k] = array('details' => SessionManager::fetch($s_id));
						$courses_tree[$cat]['sessions'][$k]['courses'] = UserManager::get_courses_list_by_session(api_get_user_id(), $s_id);
					}
				}
			}
		}
		
		$list = '';
		foreach ($personal_course_list as $my_course) {
			
			$thisCourseDbName 		= $my_course['db'];
			$thisCourseSysCode 		= $my_course['k'];
			$thisCoursePublicCode 	= $my_course['c'];
			$thisCoursePath 		= $my_course['d'];
			$sys_course_path 		= api_get_path(SYS_COURSE_PATH);
			$dbname 				= $my_course['k'];
			$status 				= array();
			$status[$dbname] 		= $my_course['s'];
		
			$nbDigestEntries = 0; // Number of entries already collected.
			if ($maxCourse < $maxValvas) {
				$maxValvas = $maxCourse;
			}
			if ($maxCourse > 0) {
				$courses[$thisCourseSysCode]['coursePath'] = $thisCoursePath;
				$courses[$thisCourseSysCode]['courseCode'] = $thisCoursePublicCode;
			}
		
			/*  Announcements */
		
			$course_database = $my_course['db'];
			$course_tool_table = Database::get_course_table(TABLE_TOOL_LIST, $course_database);
			$query = "SELECT visibility FROM $course_tool_table WHERE link = 'announcements/announcements.php' AND visibility = 1";
			$result = Database::query($query);
			// Collect from announcements, but only if tool is visible for the course.
			if ($result && $maxValvas > 0 && Database::num_rows($result) > 0) {
				// Search announcements table.
				// Take the entries listed at the top of advalvas/announcements tool.
				$course_announcement_table = Database::get_course_table(TABLE_ANNOUNCEMENT);
				$sqlGetLastAnnouncements = "SELECT end_date publicationDate, content
		                                            FROM ".$course_announcement_table;
				switch (CONFVAL_limitPreviewTo) {
					case SCRIPTVAL_NewEntriesOfTheDay :
						$sqlGetLastAnnouncements .= "WHERE DATE_FORMAT(end_date,'%Y %m %d') >= '".date('Y m d')."'";
						break;
					case SCRIPTVAL_NoTimeLimit :
						break;
					case SCRIPTVAL_NewEntriesOfTheDayOfLastLogin :
						// take care mysql -> DATE_FORMAT(time,format) php -> date(format,date)
						$sqlGetLastAnnouncements .= "WHERE DATE_FORMAT(end_date,'%Y %m %d') >= '".date('Y m d', $_user['lastLogin'])."'";
				}
				$sqlGetLastAnnouncements .= "ORDER BY end_date DESC LIMIT ".$maxValvas;
				$resGetLastAnnouncements = Database::query($sqlGetLastAnnouncements);
				if ($resGetLastAnnouncements) {
					while ($annoncement = Database::fetch_array($resGetLastAnnouncements)) {
						$keyTools = 'valvas';
						$keyTime = $annoncement['publicationDate'];
						$keyCourse = $thisCourseSysCode;
						$digest[$$orderKey[0]][$$orderKey[1]][$$orderKey[2]][] = @htmlspecialchars(api_substr(strip_tags($annoncement['content']), 0, CONFVAL_NB_CHAR_FROM_CONTENT), ENT_QUOTES, $charset);
						$nbDigestEntries ++; // summary has same order as advalvas
					}
				}
			}
		
			/* Agenda */
		
			$course_database = $my_course['db'];
			$course_tool_table = Database :: get_course_table(TABLE_TOOL_LIST, $course_database);
			$query = "SELECT visibility FROM $course_tool_table WHERE link = 'calendar/agenda.php' AND visibility = 1";
			$result = Database::query($query);
			$thisAgenda = $maxCourse - $nbDigestEntries; // New max entries for agenda.
			if ($maxAgenda < $thisAgenda) {
				$thisAgenda = $maxAgenda;
			}
			// Collect from agenda, but only if tool is visible for the course.
			if ($result && $thisAgenda > 0 && Database::num_rows($result) > 0) {
				$tableCal = $courseTablePrefix.$thisCourseDbName.$_configuration['db_glue'].'calendar_event';
				$sqlGetNextAgendaEvent = "SELECT start_date, title content, start_time
		                                            FROM $tableCal
		                                            WHERE start_date >= CURDATE()
		                                            ORDER BY start_date, start_time
		                                            LIMIT $maxAgenda";
				$resGetNextAgendaEvent = Database::query($sqlGetNextAgendaEvent);
				if ($resGetNextAgendaEvent) {
					while ($agendaEvent = Database::fetch_array($resGetNextAgendaEvent)) {
						$keyTools = 'agenda';
						$keyTime = $agendaEvent['start_date'];
						$keyCourse = $thisCourseSysCode;
						$digest[$$orderKey[0]][$$orderKey[1]][$$orderKey[2]][] = @htmlspecialchars(api_substr(strip_tags($agendaEvent['content']), 0, CONFVAL_NB_CHAR_FROM_CONTENT), ENT_QUOTES, $charset);
						$nbDigestEntries ++; // Summary has same order as advalvas.
					}
				}
			}
		} // End while mycourse...
		
		
		if (is_array($courses_tree)) {
			foreach ($courses_tree as $key => $category) {
				if ($key == 0) {
					// Sessions and courses that are not in a session category.
					if (!isset($_GET['history'])) {
						// If we're not in the history view...
						CourseManager :: display_special_courses(api_get_user_id(), $this->load_directories_preview);
						CourseManager :: display_courses(api_get_user_id(), $this->load_directories_preview);
					}
					// Independent sessions.
					foreach ($category['sessions'] as $session) {
		
						// Don't show empty sessions.
						if (count($session['courses']) < 1) {
							continue;
						}
		
						// Courses inside the current session.
						$date_session_start = $session['details']['date_start'];
						$days_access_before_beginning  = $session['details']['nb_days_access_before_beginning'] * 24 * 3600;
		
						$session_now = time();
						$html_courses_session = '';
						$count_courses_session = 0;
						foreach ($session['courses'] as $course) {
							$is_coach_course = api_is_coach($session['details']['id'], $course['code']);
							$allowed_time = 0;
							if ($date_session_start != '0000-00-00') {
								if ($is_coach_course) {
									$allowed_time = api_strtotime($date_session_start) - $days_access_before_beginning;
								} else {
									$allowed_time = api_strtotime($date_session_start);
								}
							}
							if ($session_now > $allowed_time) {
								//read only and accesible
								if (api_get_setting('hide_courses_in_sessions') == 'false') {
									$c = CourseManager :: get_logged_user_course_html($course, $session['details']['id'], 'session_course_item', true, $this->load_directories_preview);
									//$c = CourseManager :: get_logged_user_course_html($course, $session['details']['id'], 'session_course_item',($session['details']['visibility']==3?false:true));
									$html_courses_session .= $c[1];
								}
								$count_courses_session++;
							}
						}
		
						if ($count_courses_session > 0) {
							echo '<div class="userportal-session-item"><ul class="session_box">';
							echo '<li class="session_box_title" id="session_'.$session['details']['id'].'" >';
							echo Display::return_icon('window_list.png', get_lang('Expand').'/'.get_lang('Hide'), array('width' => '48px', 'align' => 'absmiddle', 'id' => 'session_img_'.$session['details']['id'])) . ' ';
		
							$s = Display :: get_session_title_box($session['details']['id']);
							$extra_info = (!empty($s['coach']) ? $s['coach'].' | ' : '').$s['dates'];
							/*if ($session['details']['visibility'] == 3) {
							 $session_link = $s['title'];
							} else {*/
							$session_link = Display::tag('a',$s['title'], array('href'=>api_get_path(WEB_CODE_PATH).'session/?session_id='.$session['details']['id']));
							//}
							echo Display::tag('span',$session_link. ' </span> <span style="padding-left: 10px; font-size: 90%; font-weight: normal;">'.$extra_info);
							if (api_is_platform_admin()) {
								echo '<div style="float:right;"><a href="'.api_get_path(WEB_CODE_PATH).'admin/resume_session.php?id_session='.$session['details']['id'].'">'.
								Display::return_icon('edit.png', get_lang('Edit'), array('align' => 'absmiddle'),22).'</a></div>';
							}
							echo '</li>';
							if (api_get_setting('hide_courses_in_sessions') == 'false') {
								echo $html_courses_session;
							}
							echo '</ul></div>';
						}
					}
				} else {
					// All sessions included in.
					if (!empty($category['details'])) {
						$count_courses_session = 0;
						$html_sessions = '';
						foreach ($category['sessions'] as $session) {
							// Don't show empty sessions.
							if (count($session['courses']) < 1) {
								continue;
							}
							$date_session_start = $session['details']['date_start'];
							$days_access_before_beginning  = $session['details']['nb_days_access_before_beginning'] * 24 * 3600;
							$session_now = time();
							$html_courses_session = '';
							$count = 0;
							foreach ($session['courses'] as $course) {
								$is_coach_course = api_is_coach($session['details']['id'], $course['code']);
								if ($is_coach_course) {
									$allowed_time = api_strtotime($date_session_start) - $days_access_before_beginning;
								} else {
									$allowed_time = api_strtotime($date_session_start);
								}
								if ($session_now > $allowed_time) {
									$c = CourseManager :: get_logged_user_course_html($course, $session['details']['id'], 'session_course_item');
									$html_courses_session .= $c[1];
									$count_courses_session++;
									$count++;
								}
							}
		
							if ($count > 0) {
								$s = Display :: get_session_title_box($session['details']['id']);
								$html_sessions .= '<ul class="sub_session_box" id="session_'.$session['details']['id'].'">';
								$html_sessions .= '<li class="sub_session_box_title" id="session_'.$session['details']['id'].'">';
								//$html_sessions .= Display::return_icon('div_hide.gif', get_lang('Expand').'/'.get_lang('Hide'), array('align' => 'absmiddle', 'id' => 'session_img_'.$session['details']['id'])) . ' ';
								$html_sessions .= Display::return_icon('window_list.png', get_lang('Expand').'/'.get_lang('Hide'), array('width' => '48px', 'align' => 'absmiddle', 'id' => 'session_img_'.$session['details']['id'])) . ' ';
		
								$session_link = Display::tag('a',$s['title'], array('href'=>api_get_path(WEB_CODE_PATH).'session/?session_id='.$session['details']['id']));
								$html_sessions .=  '<span>' . $session_link. ' </span> ';
								$html_sessions .=  '<span style="padding-left: 10px; font-size: 90%; font-weight: normal;">';
								$html_sessions .=  (!empty($s['coach']) ? $s['coach'].' | ' : '').$s['dates'];
								$html_sessions .=  '</span>';
		
								if (api_is_platform_admin()) {
									$html_sessions .=  '<div style="float: right;"><a href="'.api_get_path(WEB_CODE_PATH).'admin/resume_session.php?id_session='.$session['details']['id'].'">'.Display::return_icon('edit.png', get_lang('Edit'), array('align' => 'absmiddle'),22).'</a></div>';
								}
		
								$html_sessions .= '</li>';
								$html_sessions .= $html_courses_session;
								$html_sessions .= '</ul>';
							}
						}
		
						if ($count_courses_session > 0) {
		
							echo '<div class="userportal-session-category-item" id="session_category_'.$category['details']['id'].'">';
							echo '<div class="session_category_title_box" id="session_category_title_box_'.$category['details']['id'].'" style="color: #555555;">';
		
							echo Display::return_icon('folder_blue.png', get_lang('SessionCategory'), array('width'=>'48px', 'align' => 'absmiddle'));
		
							if (api_is_platform_admin()) {
								echo'<div style="float: right;"><a href="'.api_get_path(WEB_CODE_PATH).'admin/session_category_edit.php?&id='.$category['details']['id'].'">'.Display::return_icon('edit.png', get_lang('Edit'), array(),22).'</a></div>';
							}
		
							echo '<span id="session_category_title">';
							echo $category['details']['name'];
							echo '</span>';
		
							echo '<span style="padding-left: 10px; font-size: 90%; font-weight: normal;">';
							if ($category['details']['date_end'] != '0000-00-00') {
								printf(get_lang('FromDateXToDateY'),$category['details']['date_start'],$category['details']['date_end']);
							}
							echo '</span></div>';
		
							echo $html_sessions;
							echo '</div>';
						}
					}
				}
			}
		}
	}
}