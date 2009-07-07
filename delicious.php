<?php
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004-2008 Dokeos SPRL
	Copyright (c) 2003-2005 Ghent University (UGent)
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
* Main script for the links tool.
*
* Features:
* - Organize links into categories;
* - favorites/bookmarks-like interface;
* - move links up/down within a category;
* - move categories up/down;
* - expand/collapse all categories (except the main "non"-category);
* - add link to 'root' category => category-less link is always visible.
*
* @author Patrick Cool, main author, completely rewritten
* @author Rene Haentjens, added CSV file import (October 2004)
* @package dokeos.link
* @todo improve organisation, tables should come from database library
==============================================================================
*/

/*
==============================================================================
		INIT SECTION
==============================================================================
*/
// name of the language file that needs to be included
$language_file = "link";

// including libraries
include("../inc/global.inc.php");
include(api_get_path(LIBRARY_PATH).'events.lib.inc.php');
include("deliciousfunctions.php");


$this_section=SECTION_COURSES;

api_protect_course_script();

// @todo change the $_REQUEST into $_POST or $_GET
// @todo remove this code
$link_submitted = (!empty($_POST['submitLink'])?$_POST['submitLink']:'');
$category_submitted = (!empty($_POST['submitCategory'])?$_POST['submitCategory']:'');
$urlview = (!empty($_GET['urlview'])?$_GET['urlview']:'');
$submitImport = (!empty($_POST['submitImport'])?$_POST['submitImport']:'');
$down = (!empty($_GET['down'])?$_GET['down']:'');
$up = (!empty($_GET['up'])?$_GET['up']:'');
$catmove = (!empty($_GET['catmove'])?$_GET['catmove']:'');
$editlink = (!empty($_REQUEST['editlink'])?$_REQUEST['editlink']:'');
$id = (!empty($_REQUEST['id'])?$_REQUEST['id']:'');
$urllink = (!empty($_REQUEST['urllink'])?$_REQUEST['urllink']:'');
$title = (!empty($_REQUEST['title'])?$_REQUEST['title']:'');
$description = (!empty($_REQUEST['description'])?$_REQUEST['description']:'');
$selectcategory = (!empty($_REQUEST['selectcategory'])?$_REQUEST['selectcategory']:'');
$submitLink = (!empty($_REQUEST['submitLink'])?$_REQUEST['submitLink']:'');
$action = (!empty($_REQUEST['action'])?$_REQUEST['action']:'');
$category_title = (!empty($_REQUEST['category_title'])?$_REQUEST['category_title']:'');
$submitCategory = (!empty($_REQUEST['submitCategory'])?$_REQUEST['submitCategory']:'');

$nameTools = get_lang('Links');

	if (isset($_GET['action']) && $_GET['action']=='addlink')
	{
		$nameTools = '';
		$interbreadcrumb[] = array ('url' => 'delicious.php', 'name' => get_lang('Links'));
		$interbreadcrumb[] = array ('url' => 'delicious.php?action=addlink', 'name' => get_lang('AddLink'));
	}	
	
	if (isset($_GET['action']) && $_GET['action']=='addcategory') 
	{
		$nameTools = '';
		$interbreadcrumb[] = array ('url' => 'delicious.php', 'name' => get_lang('Links'));
		$interbreadcrumb[] = array ('url' => 'delicious.php?action=addcategory', 'name' => get_lang('AddCategory'));
	}
	
	if (isset($_GET['action']) && $_GET['action']=='editlink') 
	{
		$nameTools = '';
		$interbreadcrumb[] = array ('url' => 'delicious.php', 'name' => get_lang('Links'));
		$interbreadcrumb[] = array ('url' => '#', 'name' => get_lang('EditLink'));
	}	 	
	
		
		
// Database Table definitions
$tbl_link = Database::get_course_table(TABLE_LINK);
$tbl_categories = Database::get_course_table(TABLE_LINK_CATEGORY);

//statistics
event_access_tool(TOOL_LINK);

Display::display_header($nameTools, 'Links');
?>
<script type="text/javascript">
/* <![CDATA[ */
function MM_popupMsg(msg) { //v1.0
  confirm(msg);
}
/* ]]> */
</script>

<?php



/*
-----------------------------------------------------------
	Action Handling
-----------------------------------------------------------
*/
$nameTools = get_lang("Links");

if(isset($_GET['action']))
{
	switch($_GET['action'])
	{
		case "addlink":		
			if($link_submitted)
			{
				if(!addlinkcategory("link"))	// here we add a link
				{
					unset($submitLink);
				}
			}
			break;
		case "addcategory":		
			if($category_submitted)
			{
				if(!addlinkcategory("category"))	// here we add a category
				{
					unset($submitCategory);
				}
			}
			break;		
		case "importcsv":
			if($_POST["submitImport"])
			{
				import_csvfile();
			}
			break;
		case "deletelink":
			deletelinkcategory("link"); // here we delete a link
			break;
			
		case "deletecategory":
			deletelinkcategory("category"); // here we delete a category
			break;
		case "editlink":
			editlinkcategory("link"); // here we edit a link
			break;
		case "editcategory":
			editlinkcategory("category"); // here we edit a category
			break;
		case "visible":
			change_visibility($_GET['id'],$_GET['scope']); // here we edit a category
			break;
		case "invisible":
			change_visibility($_GET['id'],$_GET['scope']); // here we edit a category
			break;
	}
}

/*
-----------------------------------------------------------
	Introduction section
-----------------------------------------------------------
*/
Display::display_introduction_section(TOOL_LINK);


if (is_allowed_to_edit() and isset($_GET['action']))
{
	// Displaying the correct title and the form for adding a category or link. This is only shown when nothing
	// has been submitted yet, hence !isset($submitLink)
	if (($_GET['action']=="addlink" or $_GET['action']=="editlink") and empty($_POST['submitLink']))
	{
		echo "<h4>";
		if ($_GET['action']=="addlink")
			{echo get_lang("LinkAdd");}
		else
			{echo get_lang("LinkMod");}
		echo "</h4>\n\n";
		if ($category=="")
			{$category=0;}
		echo "<form method=\"post\" action=\"".api_get_self()."?action=".$_GET['action']."&amp;urlview=".$urlview."\">";
		if ($_GET['action']=="editlink")
		{
			echo "<input type=\"hidden\" name=\"id\" value=\"".$_GET['id']."\" />";
		}

		echo "<table><tr>"
			. "<td align=\"right\">URL<span class=\"required\">*</span>  :</td>"
			. "<td><input type=\"text\" name=\"urllink\" size=\"50\" value=\"" . (empty($urllink)?'http://':htmlentities($urllink)) . "\" /></td>"			. "</tr>";
		echo "<tr>"
				. "<td align=\"right\">" . get_lang("LinkName") . " :</td>"
				. "<td><input type=\"text\" name=\"title\" size=\"50\" value=\"" . htmlentities($title,ENT_QUOTES,$charset) . "\" /></td>"
				. "</tr>"
				. "<tr>" .
				"<td align=\"right\" valign=\"top\">" . get_lang("Description") . " :</td>" .
				"<td><textarea rows=\"3\" cols=\"50\" name=\"description\">" .
				htmlentities($description,ENT_QUOTES,$charset) . "</textarea></td></tr>";

		$sqlcategories="SELECT * FROM ".$tbl_categories." ORDER BY display_order DESC";
		$resultcategories = api_sql_query($sqlcategories)or die("Error: " . mysql_error());

		if(mysql_num_rows($resultcategories))
		{
			echo	"<tr><td align=\"right\">".get_lang("Category")." :</td><td>",
					"<select name=\"selectcategory\">",
					"<option value=\"0\">--</option>";

			while ($myrow = mysql_fetch_array($resultcategories))
			{
				echo "<option value=\"".$myrow["id"]."\"";
				if ($myrow["id"]==$category)
					{echo " selected";}
				echo ">".$myrow["category_title"]."</option>";
			}
			echo "</select></td></tr>";
		}

		echo "<tr><td align=\"right\">".get_lang("OnHomepage")." ? </td><td><input class=\"checkbox\" type=\"checkbox\" name=\"onhomepage\" id=\"onhomepage\" value=\"1\" $onhomepage><label for=\"onhomepage\"> ".get_lang("Yes")."</label></td></tr>";

		echo "<tr><td></td><td><input type=\"Submit\" name=\"submitLink\" value=\"".get_lang("Ok")."\" /></td></tr>",
			"</table>",
			"</form>";
	}
	elseif(($_GET['action']=="addcategory" or $_GET['action']=="editcategory") and !$submitCategory)
	{
		echo "<h4>";
		if ($_GET['action']=="addcategory")
			{echo get_lang("CategoryAdd");}
		else
			{echo get_lang("CategoryMod");}
		echo "</h4>\n\n";
		echo "<form method=\"post\" action=\"".api_get_self()."?action=".$_GET['action']."&amp;urlview=".$urlview."\">";
		if ($_GET['action']=="editcategory")
		{
			echo "<input type=\"hidden\" name=\"id\" value=\"".$id."\" />";
		}
		echo "<table><tr>",
			"<td align=\"right\">".get_lang("CategoryName")."<span class=\"required\">*</span>  :</td>",
			"<td><input type=\"text\" name=\"category_title\" size=\"50\" value=\"",htmlentities($category_title,ENT_QUOTES,$charset)."\" /></td>",
			"</tr>",
			"<tr><td align=\"right\" valign=\"top\">".get_lang("Description")." :</td>",
			"<td><textarea rows=\"3\" cols=\"50\" name=\"description\">",htmlentities($description,ENT_QUOTES,$charset)."</textarea></td></tr>",
			"<tr><td></td><td><input type=\"Submit\" name=\"submitCategory\" value=\"".get_lang("Ok")."\" /></td></tr>",
			"</table>",
			"</form>";
	}
}


if (!empty($down))
	{
	movecatlink($down);
	}
if (!empty($up))
	{
	movecatlink($up);
	}

if (empty($_GET['action']) || ($_GET['action']!='editlink' && $_GET['action']!='addcategory' && $_GET['action']!='addlink') || $link_submitted || $category_submitted)
{	
	/*
	-----------------------------------------------------------
		Action Links
	-----------------------------------------------------------
	*/
	
	if(is_allowed_to_edit())
	{
		echo Display::return_icon('file_html_new.gif')." <a href=\"".api_get_self()."?".api_get_cidreq()."&action=addlink&amp;category=".(!empty($category)?$category:'')."&amp;urlview=$urlview\">".get_lang("LinkAdd")."</a>\n";
		echo Display::return_icon('folder_new.gif')." <a href=\"".api_get_self()."?".api_get_cidreq()."&action=addcategory&amp;urlview=".$urlview."\">".get_lang("CategoryAdd")."</a>\n";
		   /* "<a href=\"".api_get_self()."?".api_get_cidreq()."&action=importcsv&amp;urlview=".$urlview."\">".get_lang('CsvImport')."</a>\n", // RH*/
	}
	//making the show none / show all links. Show none means urlview=0000 (number of zeros depending on the
	//number of categories). Show all means urlview=1111 (number of 1 depending on teh number of categories).
	$sqlcategories="SELECT * FROM ".$tbl_categories." ORDER BY display_order DESC";
	$resultcategories=api_sql_query($sqlcategories);
	$aantalcategories = @mysql_num_rows($resultcategories);
	echo Display::return_icon('remove.gif')." <a href=\"".api_get_self()."?".api_get_cidreq()."&urlview=";
	for($j = 1; $j <= $aantalcategories; $j++)
	{
		echo "0";
	}
	echo "\">$shownone</a>";
	echo Display::return_icon('add.gif')." <a href=\"".api_get_self()."?".api_get_cidreq()."&urlview=";
	for($j = 1; $j <= $aantalcategories; $j++)
	{
	echo "1";
	}
	echo "\">$showall</a>";
	
	$cloud = '<script type="text/javascript" src="http://feeds.delicious.com/v2/js/tags/marcyves?title=%40%40tags%40%40&icon&count=100&sort=alpha&flow=cloud&name&color=73adff-3274d0&size=12-35"></script>';
	
	echo $cloud;

	//Starting the table which contains the categories
	
	$sqlcategories="SELECT * FROM ".$tbl_categories." ORDER BY display_order DESC";
	$resultcategories=api_sql_query($sqlcategories);
	
	echo '<br /><br /><table class="data_table">';
	// displaying the links which have no category (thus category = 0 or NULL), if none present this will not be displayed
		$sqlLinks = "SELECT * FROM ".$tbl_link." WHERE category_id=0 or category_id IS NULL";
		$result = api_sql_query($sqlLinks);
		$numberofzerocategory=mysql_num_rows($result);
		
		if ($numberofzerocategory!==0)
		{
			echo "<tr><th style=\"font-weight: bold; text-align:left;padding-left: 10px;\"><i>".get_lang('General')."</i></th></tr>";
			echo '</table>';	
			showlinksofcategory(0);		
		}
		
	$i=0;
	$catcounter=1;
	$view="0";
	
	while ($myrow=@mysql_fetch_array($resultcategories))
	{
		if (!isset($urlview))
		{
			// No $view set in the url, thus for each category link it should be all zeros except it's own
			makedefaultviewcode($i);
		}
		else
		{
			$view=$urlview;
			$view[$i]="1";
		}
		// if the $urlview has a 1 for this categorie, this means it is expanded and should be desplayed as a
		// - instead of a +, the category is no longer clickable and all the links of this category are displayed
		$myrow["description"]=text_filter($myrow["description"]);
		
		if ($urlview[$i]=="1")
		{
			$newurlview=$urlview;
			$newurlview[$i]="0";
			
			echo '<tr>';
				echo '<table class="data_table">';
				echo '<tr>';			
					echo '<th width="81%"  style="font-weight: bold; text-align:left;padding-left: 5px;">';
					echo '<a href="'.api_get_self()."?".api_get_cidreq()."&urlview=".$newurlview."\">";
					echo "<img src=../img/remove.gif>&nbsp;&nbsp;".htmlentities($myrow["category_title"],ENT_QUOTES,$charset)."</a><br/>&nbsp;&nbsp;&nbsp;".$myrow["description"];
					
					if (is_allowed_to_edit())
						{	
							echo '<th>';	
							showcategoryadmintools($myrow["id"]);
							echo '</th>';			
						}
					echo '</th>';						
				echo '</tr>';
				echo '</table>';		
				echo showlinksofcategory($myrow["id"]);						
			echo '</tr>';			
		}
		else
		{		
			echo '<tr>';
				echo '<table class="data_table">';
				echo '<tr>';			
					echo '<th width="81%" style="font-weight: bold; text-align:left;padding-left: 5px;"><a href="'.api_get_self()."?".api_get_cidreq()."&urlview=";
					echo is_array($view)?implode('',$view):$view;
					echo "\"><img src=../img/add.gif>&nbsp;&nbsp;".  htmlentities($myrow["category_title"],ENT_QUOTES,$charset);
					echo'</a><br />&nbsp;&nbsp;&nbsp;';
					echo $myrow["description"];						
						if (is_allowed_to_edit())
						{		
							echo '<th style="text-align:center;">';	
							showcategoryadmintools($myrow["id"]);			
							echo '</th>';
						}
					echo '</th>';			
				echo '</tr>';
				
				
				echo '</table>';						
			echo '</tr>';				
		}
		// displaying the link of the category
		$i++;
	}
	echo '</table>';
	////////////////////////////////////////////////////////////////////////////
}		
		
		


Display::display_footer();

?>