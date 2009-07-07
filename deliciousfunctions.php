<?php
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004-2008 Dokeos S.A.
	Copyright (c) 2003 Ghent University (UGent)
	Copyright (c) 2001 Universite catholique de Louvain (UCL)

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact address: Dokeos, rue du Corbeau, 108, B-1030 Brussels, Belgium
	Mail: info@dokeos.com
==============================================================================
*/
/**
==============================================================================
* Function library for the links tool.
*
* This is a complete remake of the original link tool.
* New features:
* - Organize links into categories;
* - favorites/bookmarks interface;
* - move links up/down within a category;
* - move categories up/down;
* - expand/collapse all categories;
* - add link to 'root' category => category-less link is always visible.
*
*	@author Patrick Cool, complete remake (December 2003 - January 2004)
*	@author Rene Haentjens, CSV file import (October 2004)
*	@package dokeos.link
==============================================================================
*/

/*
==============================================================================
		FUNCTIONS
==============================================================================
*/

/**
* Used to add a link or a category
* @param string $type, "link" or "category"
* @todo replace strings by constants
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function addlinkcategory($type)
{
	global $catlinkstatus;
	global $msgErr;

	$ok = true;

	if ($type == "link")
	{
		$tbl_link = Database :: get_course_table(TABLE_DELICIOUS);

		$title = $_POST['title'];
		$urllink = $_POST['urllink'];
		$description = $_POST['description'];
		$selectcategory = $_POST['selectcategory'];
		if ($_POST['onhomepage'] == '')
		{
			$onhomepage = 0;
		}
		else
		{
			$onhomepage = $_POST['onhomepage'];
		}

		$urllink = trim($urllink);
		$title = trim($title);
		$description = trim($description);

		// if title is empty, an error occurs
		if (empty ($urllink) OR $urllink == 'http://')
		{
			$msgErr = get_lang('GiveURL');

			Display::display_error_message(get_lang('GiveURL'));

			$ok = false;
		}
		// if the title is empty, we use the url as the title
		else
		{
			if (empty ($title))
			{
				$title = $urllink;
			}

			// we check weither the $url starts with http://, if not we add this
			if (!strstr($urllink, '://'))
			{
				$urllink = "http://".$urllink;
			}

			// looking for the largest order number for this category
			$result = api_sql_query("SELECT MAX(display_order) FROM  ".$tbl_link." WHERE category_id='".$_POST['selectcategory']."'");

			list ($orderMax) = mysql_fetch_row($result);

			$order = $orderMax +1;

			$sql = "INSERT INTO ".$tbl_link." (url, title, description, category_id,display_order, on_homepage) VALUES ('$urllink','$title','$description','$selectcategory','$order', '$onhomepage')";
			$catlinkstatus = get_lang('LinkAdded');
			api_sql_query($sql, __FILE__, __LINE__);
			unset ($urllink, $title, $description, $selectcategory);

			Display::display_confirmation_message(get_lang('LinkAdded'));
		}
	}
	elseif ($type == "category")
	{
		$tbl_categories = Database :: get_course_table(TABLE_DELICIOUS_CATEGORY);

		$category_title = trim($_POST['category_title']);
		$description = trim($_POST['description']);

		if (empty ($category_title))
		{
			$msgErr = get_lang('GiveCategoryName');

			Display::display_error_message(get_lang('GiveCategoryName'));

			$ok = false;
		}
		else
		{
			// looking for the largest order number for this category
			$result = api_sql_query("SELECT MAX(display_order) FROM  ".$tbl_categories."");

			list ($orderMax) = mysql_fetch_row($result);

			$order = $orderMax +1;

			$sql = "INSERT INTO ".$tbl_categories." (category_title, description, display_order) VALUES ('$category_title','$description', '$order')";
			api_sql_query($sql, __FILE__, __LINE__);

			$catlinkstatus = get_lang('CategoryAdded');

			unset ($category_title, $description);

			Display::display_confirmation_message(get_lang('CategoryAdded'));
		}
	}

	// "WHAT'S NEW" notification : update last tool Edit
	if ($type == "link")
	{
		global $_user;
		global $_course;
		global $nameTools;

		api_item_property_update($_course, TOOL_LINK, mysql_insert_id(), "LinkAdded", $_user['user_id']);
	}

	return $ok;
}
// End of the function addlinkcategory

/**
* Used to delete a link or a category
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function deletelinkcategory($type)
{
	global $catlinkstatus;
	global $_course;
	global $_user;
	$tbl_link = Database :: get_course_table(TABLE_DELICIOUS);
	$tbl_categories = Database :: get_course_table(TABLE_DELICIOUS_CATEGORY);
	$TABLE_ITEM_PROPERTY = Database :: get_course_table(TABLE_ITEM_PROPERTY);

	if ($type == "link")
	{
		global $id;
		// -> items are no longer fysically deleted, but the visibility is set to 2 (in item_property). This will
		// make a restore function possible for the platform administrator
		//$sql="DELETE FROM $tbl_link WHERE id='".$_GET['id']."'";
		api_item_property_update($_course, TOOL_LINK, $id, "delete", $_user['user_id']);
		$catlinkstatus = get_lang("LinkDeleted");
		unset ($id);

		Display::display_confirmation_message(get_lang('LinkDeleted'));
	}
	if ($type == "category")
	{
		global $id;

		// first we delete the category itself and afterwards all the links of this category.
		$sql = "DELETE FROM ".$tbl_categories." WHERE id='".$_GET['id']."'";
		api_sql_query($sql, __FILE__, __LINE__);
		$sql = "DELETE FROM ".$tbl_link." WHERE category_id='".$_GET['id']."'";
		$catlinkstatus = get_lang('CategoryDeleted');
		unset ($id);
		api_sql_query($sql, __FILE__, __LINE__);

		Display::display_confirmation_message(get_lang('CategoryDeleted'));
	}
}

/**
* Used to edit a link or a category
* @todo rewrite the whole links tool because it is becoming completely cluttered,
* 		code does not follow the coding conventions, does not use html_quickform, ...
* 		some features were patched in
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
* @todo replace the globals with the appropriate $_POST or $_GET values
*/
function editlinkcategory($type)
{
	global $catlinkstatus;
	global $id;
	global $submitLink;
	global $submitCategory;
	global $_user;
	global $_course;
	global $nameTools;
	global $urllink;
	global $title;
	global $description;
	global $category;
	global $selectcategory;
	global $description;
	global $category_title;
	global $onhomepage;

	$tbl_link 		= Database :: get_course_table(TABLE_DELICIOUS);
	$tbl_categories = Database :: get_course_table(TABLE_DELICIOUS_CATEGORY);

	if ($type == "link")
	{
		// this is used to populate the link-form with the info found in the database
		$sql = "SELECT * FROM ".$tbl_link." WHERE id='".$_GET['id']."'";
		$result = api_sql_query($sql, __FILE__, __LINE__);
		if ($myrow = mysql_fetch_array($result))
		{
			$urllink = $myrow["url"];
			$title = $myrow["title"];
			$description = $myrow["description"];
			$category = $myrow["category_id"];
			if ($myrow["on_homepage"] <> 0)
			{
				$onhomepage = "checked";
			}
		}
		// this is used to put the modified info of the link-form into the database
		if ($_POST['submitLink'])
		{
			if ($_POST['onhomepage'] == '')
			{
				$onhomepage = 0;
			}
			else
			{
				$onhomepage = $_POST['onhomepage'];
			}

			// finding the old category_id
			$sql = "SELECT * FROM ".$tbl_link." WHERE id='".$_POST['id']."'";
			$result = api_sql_query($sql, __FILE__, __LINE__);
			$row = mysql_fetch_array($result);
			$category_id = $row['category_id'];

			if ($category_id <> $_POST['selectcategory'])
			{
				$sql = "SELECT MAX(display_order) FROM ".$tbl_link." WHERE category_id='".$_POST['selectcategory']."'";
				$result = api_sql_query($sql);
				list ($max_display_order) = mysql_fetch_row($result);
				$max_display_order ++;
			}
			else
			{
				$max_display_order = $row['display_order'];
			}

			$sql = "UPDATE ".$tbl_link." set url='".$_POST['urllink']."', title='".$_POST['title']."', description='".$_POST['description']."', category_id='".$_POST['selectcategory']."', display_order='".$max_display_order."', on_homepage='".$_POST['onhomepage']."' WHERE id='".$_POST['id']."'";
			api_sql_query($sql, __FILE__, __LINE__);

			// "WHAT'S NEW" notification: update table last_toolEdit
			api_item_property_update($_course, TOOL_LINK, $_POST['id'], "LinkUpdated", $_user['user_id']);

			Display::display_confirmation_message(get_lang('LinkModded'));
		}
	}
	if ($type == "category")
	{
		// this is used to populate the category-form with the info found in the database
		if (!$submitCategory)
		{
			$sql = "SELECT * FROM ".$tbl_categories." WHERE id='".$_GET['id']."'";
			$result = api_sql_query($sql, __FILE__, __LINE__);
			if ($myrow = mysql_fetch_array($result))
			{
				$category_title = $myrow["category_title"];
				$description = $myrow["description"];
			}
		}
		// this is used to put the modified info of the category-form into the database
		if ($submitCategory)
		{
			$sql = "UPDATE ".$tbl_categories." set category_title='".$_POST['category_title']."', description='".$_POST['description']."' WHERE id='".$_POST['id']."'";
			api_sql_query($sql, __FILE__, __LINE__);
			Display::display_confirmation_message(get_lang('CategoryModded'));
		}


	}
}
// END of function editlinkcat

/**
* creates a correct $view for in the URL
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function makedefaultviewcode($locatie)
{
	global $aantalcategories;
	global $view;

	for ($j = 0; $j <= $aantalcategories -1; $j ++)
	{
		$view[$j] = 0;
	}
	$view[intval($locatie)] = "1";
}
// END of function makedefaultviewcode

/**
* changes the visibility of a link
* @todo add the changing of the visibility of a course
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function change_visibility($id, $scope)
{
	global $_course;
	global $_user;
	$TABLE_ITEM_PROPERTY = Database :: get_course_table(TABLE_ITEM_PROPERTY);

	if ($scope == "link")
	{
		$sqlselect = "SELECT * FROM $TABLE_ITEM_PROPERTY WHERE tool='".TOOL_LINK."' and ref='".$id."'";
		$result = api_sql_query($sqlselect);
		$row = mysql_fetch_array($result);
		api_item_property_update($_course, TOOL_LINK, $id, $_GET['action'], $_user['user_id']);
	}

	Display::display_confirmation_message(get_lang('VisibilityChanged'));
}

/**
* displays all the links of a given category.
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function showlinksofcategory($catid)
{
	global $is_allowed, $charset, $urlview, $up, $down;
	$tbl_link = Database :: get_course_table(TABLE_DELICIOUS);

	$TABLE_ITEM_PROPERTY = Database :: get_course_table(TABLE_ITEM_PROPERTY);

	$sqlLinks = "SELECT * FROM ".$tbl_link." link, ".$TABLE_ITEM_PROPERTY." itemproperties WHERE itemproperties.tool='".TOOL_LINK."' AND link.id=itemproperties.ref AND  link.category_id='".$catid."' AND (itemproperties.visibility='0' OR itemproperties.visibility='1')ORDER BY link.display_order DESC";
	$result = api_sql_query($sqlLinks);
	$numberoflinks = mysql_num_rows($result);
	
	echo '<table class="data_table" width="100%">';
	$i = 1;
	while ($myrow = mysql_fetch_array($result))
	{
		if($i%2==0) $css_class = 'row_odd';
		else $css_class = 'row_even';
		
		$myrow[3] = text_filter($myrow[3]);
		if ($myrow['visibility'] == '1')
		{
			echo "<tr class='".$css_class."'>", "<td align=\"center\" valign=\"middle\" width=\"15\">", "<a href=\"link_goto.php?".api_get_cidreq()."&link_id=", $myrow[0], "&amp;link_url=", urlencode($myrow[1]), "\" target=\"_blank\">", "<img src=\"../../main/img/file_html.gif\" border=\"0\" alt=\"".get_lang('Links')."\"/>", "</a></td>", "<td width=\"80%\" valign=\"top\">", "<a href=\"link_goto.php?".api_get_cidreq()."&link_id=", $myrow[0], "&amp;link_url=", urlencode($myrow[1]), "\" target=\"_blank\">", htmlentities($myrow[2],ENT_QUOTES,$charset), "</a>\n", "<br/>", $myrow[3], "";
		}
		else
		{
			if (api_is_allowed_to_edit())
			{
				echo "<tr class='".$css_class."'>", "<td align=\"center\" valign=\"middle\" width=\"15\">", "<a href=\"link_goto.php?".api_get_cidreq()."&link_id=", $myrow[0], "&amp;link_url=", urlencode($myrow[1]), "\" target=\"_blank\" class=\"invisible\">", Display::return_icon('file_html_na.gif', get_lang('Links')),"</a></td>", "<td width=\"80%\" valign=\"top\">", "<a href=\"link_goto.php?".api_get_cidreq()."&link_id=", $myrow[0], "&amp;link_url=", urlencode($myrow[1]), "\" target=\"_blank\"  class=\"invisible\">", htmlentities($myrow[2],ENT_QUOTES,$charset), "</a>\n", "<br />", $myrow[3], "";
			}
		}
		
		echo '<td style="text-align:center;">';
		if (api_is_allowed_to_edit())
		{
			echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&action=editlink&amp;category=".(!empty($category)?$category:'')."&amp;id=$myrow[0]&amp;urlview=$urlview\"  title=\"".get_lang('Modify')."\"  >", "<img src=\"../img/edit.gif\" border=\"0\" alt=\"", get_lang('Modify'), "\" />", "</a>";			
			echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&action=deletelink&amp;id=", $myrow[0], "&amp;urlview=", $urlview, "\" onclick=\"javascript:if(!confirm('".get_lang('LinkDelconfirm')."')) return false;\"  title=\"".get_lang('Delete')."\" >", "<img src=\"../img/delete.gif\" border=\"0\" alt=\"", get_lang('Delete'), "\" />", "</a>";
			// DISPLAY MOVE UP COMMAND only if it is not the top link
			if ($i != 1)
			{
				echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&urlview=".$urlview."&amp;up=", $myrow["id"], "\"  title=\"".get_lang('Up')."\"   >", "<img src=../img/up.gif border=0 alt=\"Up\"/>", "</a>\n";
			}
			else 
			{
				echo '<img src="'.api_get_path(WEB_IMG_PATH).'up_na.gif" border=0 alt="Up"/>';
			}	
			
			// DISPLAY MOVE DOWN COMMAND only if it is not the bottom link
			if ($i < $numberoflinks)
			{
				echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&urlview=".$urlview."&amp;down=".$myrow["id"]."\"  title=\"".get_lang('Down')."\" >", "<img src=\"../img/down.gif\" border=\"0\" alt=\"Down\"/>", "</a>\n";
			}
			else
			{
				echo '<img src="'.api_get_path(WEB_IMG_PATH).'down_na.gif" border=0 alt="Up"/>';	
			}
			
			if ($myrow['visibility'] == "1")
			{
				echo '<a href="link.php?'.api_get_cidreq().'&action=invisible&amp;id='.$myrow['id'].'&amp;scope=link&amp;urlview='.$urlview.'" title="'.get_lang('langVisible').'"><img src="../img/visible.gif" border="0" /></a>'; 
			}
			if ($myrow['visibility'] == "0")
			{
 				echo '<a href="link.php?'.api_get_cidreq().'&action=visible&amp;id='.$myrow['id'].'&amp;scope=link&amp;urlview='.$urlview.'" title="'.get_lang('langVisible').'"><img src="../img/invisible.gif" border="0" /></a>';
			}
		} 
		echo '</td>';
		echo '</tr>';
		$i ++;
	}
	echo '</table>';
}

/**
* displays the edit, delete and move icons
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function showcategoryadmintools($categoryid)
{
	global $urlview;
	global $aantalcategories;
	global $catcounter;
 
	echo '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=editcategory&amp;id='.$categoryid.'&amp;urlview=$amp;urlview\" title="'.get_lang('Modify').'" ><img src="../img/edit.gif" border="0" alt="'.get_lang('Modify').' "/></a>';
	echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&action=deletecategory&amp;id=", $categoryid, "&amp;urlview=$urlview\" onclick=\"javascript:if(!confirm('".get_lang('CategoryDelconfirm')."')) return false;\">", "<img src=\"../img/delete.gif\" border=\"0\" alt=\"", get_lang('Delete'), "\"/>", "</a>";

	// DISPLAY MOVE UP COMMAND only if it is not the top link	
	if ($catcounter != 1)
	{
		echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&catmove=true&amp;up=", $categoryid, "&amp;urlview=$urlview\"  title=\"".get_lang('Up')."\" >", "<img src=../img/up.gif border=0 alt=\"Up\"/>", "</a>\n";
	}
	else
	{
		echo '<img src="'.api_get_path(WEB_IMG_PATH).'up_na.gif" border=0 alt="Up"/>';	
	}	
	// DISPLAY MOVE DOWN COMMAND only if it is not the bottom link
	if ($catcounter < $aantalcategories)
	{
		echo "<a href=\"".api_get_self()."?".api_get_cidreq()."&catmove=true&amp;down=".$categoryid."&amp;urlview=$urlview\">", "<img src=\"../img/down.gif\" border=\"0\" alt=\"Down\"/>", "</a>\n";
	}
	else
	{
		echo '<img src="'.api_get_path(WEB_IMG_PATH).'down_na.gif" border=0 alt="Up"/>';
	}		
	$catcounter ++;
}

/**
* move a link or a linkcategory up or down
* @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
*/
function movecatlink($catlinkid)
{
	global $catmove;
	global $up;
	global $down;
	$tbl_link = Database :: get_course_table(TABLE_DELICIOUS);
	$tbl_categories = Database :: get_course_table(TABLE_DELICIOUS_CATEGORY);

	if (!empty($_GET['down']))
	{
		$thiscatlinkId = $_GET['down'];
		$sortDirection = "DESC";
	}
	if (!empty($_GET['up']))
	{
		$thiscatlinkId = $_GET['up'];
		$sortDirection = "ASC";
	}

	// We check if it is a category we are moving or a link. If it is a category, a querystring catmove = true is present in the url
	if ($catmove == "true")
	{
		$movetable = $tbl_categories;
		$catid = $catlinkid;
	}
	else
	{
		$movetable = $tbl_link;
		//getting the category of the link
		if(!empty($thiscatlinkId))
		{
			$sql = "SELECT category_id from ".$movetable." WHERE id='$thiscatlinkId'";
			$result = api_sql_query($sql, __FILE__, __LINE__);
			$catid = mysql_fetch_array($result);
		}
	}

	// this code is copied and modified from announcements.php
	if (!empty($sortDirection))
	{
		if (!in_array(trim(strtoupper($sortDirection)), array ('ASC', 'DESC')))
			die("Bad sort direction used."); //sanity check of sortDirection var
		if ($catmove == "true")
		{
			$sqlcatlinks = "SELECT id, display_order FROM ".$movetable." ORDER BY display_order $sortDirection";
		}
		else
		{
			$sqlcatlinks = "SELECT id, display_order FROM ".$movetable." WHERE category_id='".$catid[0]."' ORDER BY display_order $sortDirection";
		}
		$linkresult = api_sql_query($sqlcatlinks);
		while ($sortrow = mysql_fetch_array($linkresult))
		{
			// STEP 2 : FOUND THE NEXT ANNOUNCEMENT ID AND ORDER, COMMIT SWAP
			// This part seems unlogic, but it isn't . We first look for the current link with the querystring ID
			// and we know the next iteration of the while loop is the next one. These should be swapped.
			if (isset ($thislinkFound) && $thislinkFound == true)
			{
				$nextlinkId = $sortrow["id"];
				$nextlinkOrdre = $sortrow["display_order"];

				api_sql_query("UPDATE ".$movetable."
							             SET display_order = '$nextlinkOrdre'
							             WHERE id =  '$thiscatlinkId'");

				api_sql_query("UPDATE ".$movetable."
							             SET display_order = '$thislinkOrdre'
										 WHERE id =  '$nextlinkId'");

				break;
			}

			if ($sortrow["id"] == $thiscatlinkId)
			{
				$thislinkOrdre = $sortrow["display_order"];
				$thislinkFound = true;
			}
		}
	}

	Display::display_confirmation_message(get_lang('LinkMoved'));
}

/**
* CSV file import functions
* @author Rene Haentjens , Ghent University
*/
function get_cat($catname) // get category id (existing or make new)
{
	$tbl_categories = Database :: get_course_table(TABLE_DELICIOUS_CATEGORY);

	$result = api_sql_query("SELECT `id` FROM ".$tbl_categories." WHERE `category_title`='".addslashes($catname)."'", __FILE__, __LINE__);

	if (mysql_num_rows($result) >= 1 && ($row = mysql_fetch_array($result)))
		return $row['id']; // several categories with same name: take first

	$result = api_sql_query("SELECT MAX(display_order) FROM ".$tbl_categories."", __FILE__, __LINE__);
	list ($max_order) = mysql_fetch_row($result);

	api_sql_query("INSERT INTO ".$tbl_categories." (category_title, description, display_order) VALUES ('".addslashes($catname)."','','". ($max_order +1)."')", __FILE__, __LINE__);

	return mysql_insert_id();
}
/**
* CSV file import functions
* @author Rene Haentjens , Ghent University
*/
function put_link($url, $cat, $title, $description, $on_homepage, $hidden)
{
	$tbl_link = Database :: get_course_table(TABLE_DELICIOUS);

	$urleq = "url='".addslashes($url)."'";
	$cateq = "category_id=".$cat;

	$result = api_sql_query("SELECT id FROM $tbl_link WHERE ".$urleq.' AND '.$cateq, __FILE__, __LINE__);

	if (mysql_num_rows($result) >= 1 && ($row = mysql_fetch_array($result)))
	{
		api_sql_query("UPDATE $tbl_link set title='".addslashes($title)."', description='".addslashes($description)."' WHERE id='".addslashes($id = $row['id'])."'", __FILE__, __LINE__);

		$lang_link = get_lang('update_link');
		$ipu = "LinkUpdated";
		$rv = 1; // 1= upd
	}
	else // add new link
		{
		$result = api_sql_query("SELECT MAX(display_order) FROM  $tbl_link WHERE category_id='".addslashes($cat)."'", __FILE__, __LINE__);
		list ($max_order) = mysql_fetch_row($result);

		api_sql_query("INSERT INTO $tbl_link (url, title, description, category_id, display_order, on_homepage) VALUES ('".addslashes($url)."','".addslashes($title)."','".addslashes($description)."','".addslashes($cat)."','". ($max_order +1)."','".$on_homepage."')", __FILE__, __LINE__);

		$id = mysql_insert_id();
		$lang_link = get_lang('new_link');
		$ipu = "LinkAdded";
		$rv = 2; // 2= new
	}

	global $_course, $nameTools, $_user;
	api_item_property_update($_course, TOOL_LINK, $id, $ipu, $_user['user_id']);

	if ($hidden && $ipu == "LinkAdded")
		api_item_property_update($_course, TOOL_LINK, $id, "invisible", $_user['user_id']);

	return $rv;
}
/**
* CSV file import functions
* @author Rene Haentjens , Ghent University
*/
function import_link($linkdata) // url, category_id, title, description, ...
{
	// field names used in the uploaded file
	$known_fields = array ('url', 'category', 'title', 'description', 'on_homepage', 'hidden');
	$hide_fields = array ('kw', 'kwd', 'kwds', 'keyword', 'keywords');

	// all other fields are added to description, as "name:value"

	// only one hide_field is assumed to be present, <> is removed from value

	if (!($url = trim($linkdata['url'])) || !($title = trim($linkdata['title'])))
		return 0; // 0= fail

	$cat = ($catname = trim($linkdata['category'])) ? get_cat($catname) : 0;

	$regs = array(); // will be passed to ereg()
	foreach ($linkdata as $key => $value)
		if (!in_array($key, $known_fields))
			if (in_array($key, $hide_fields) && ereg('^<?([^>]*)>?$', $value, $regs)) // possibly in <...>
				if (($kwlist = trim($regs[1])) != '')
					$kw = '<i kw="'.htmlspecialchars($kwlist).'">';
				else
					$kw = '';
	// i.e. assume only one of the $hide_fields will be present
	// and if found, hide the value as expando property of an <i> tag
	elseif (trim($value)) $d .= ', '.$key.':'.$value;
	if ($d)
		$d = substr($d, 2).' - ';

	return put_link($url, $cat, $title, $kw.ereg_replace('\[((/?(b|big|i|small|sub|sup|u))|br/)\]', '<\\1>', htmlspecialchars($d.$linkdata['description'])). ($kw ? '</i>' : ''), $linkdata['on_homepage'] ? '1' : '0', $linkdata['hidden'] ? '1' : '0');
	// i.e. allow some BBcode tags, e.g. [b]...[/b]
}
/**
* CSV file import functions
* @author Rene Haentjens , Ghent University
*/
function import_csvfile()
{
	global $catlinkstatus; // feedback message to user

	if (is_uploaded_file($filespec = $_FILES['import_file']['tmp_name']) && filesize($filespec) && ($myFile = @ fopen($filespec, 'r')))
	{
		// read first line of file (column names) and find ',' or ';'
		$listsep = strpos($colnames = trim(fgets($myFile)), ',') !== FALSE ? ',' : (strpos($colnames, ';') !== FALSE ? ';' : '');

		if ($listsep)
		{
			$columns = array_map('strtolower', explode($listsep, $colnames));

			if (in_array('url', $columns) && in_array('title', $columns))
			{
				$stats = array (0, 0, 0); // fails, updates, inserts

				while (($data = fgetcsv($myFile, 32768, $listsep)))
				{
					foreach ($data as $i => $text)
						$linkdata[$columns[$i]] = $text;
					//  $linkdata['url', 'title', ...]

					$stats[import_link($linkdata)]++;
					unset ($linkdata);
				}

				$catlinkstatus = '';

				if ($stats[0])
					$catlinkstatus .= $stats[0].' '.get_lang('CsvLinesFailed');
				if ($stats[1])
					$catlinkstatus .= $stats[1].' '.get_lang('CsvLinesOld');
				if ($stats[2])
					$catlinkstatus .= $stats[2].' '.get_lang('CsvLinesNew');
			}
			else
				$catlinkstatus = get_lang('CsvFileNoURL'). ($colnames ? get_lang('CsvFileLine1').htmlspecialchars(substr($colnames, 0, 200)).'...' : '');
		}
		else
			$catlinkstatus = get_lang('CsvFileNoSeps'). ($colnames ? get_lang('CsvFileLine1').htmlspecialchars(substr($colnames, 0, 200)).'...' : '');
		fclose($myFile);
	}
	else
		$catlinkstatus = get_lang('CsvFileNotFound');
}
?>