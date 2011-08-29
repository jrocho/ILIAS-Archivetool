<?php
/* This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
   --------------------------
 
   ILIAS Archive tool
   This tool adds an [Archiv] (or a custom string)
   in front of course titles which are in archive categories
   
   (c)2010-2011 by Jan Rocho <jan@rocho.eu>
   v1.2.1
   
   Call this script:
   
   archiv.php?key=<MD5 HASH>
   
   For a dry test run (no database writes) call this script with debug=1
   
   archiv.php?key=<MD5 HASH>&debug=1
   
   This script can be called as often as need. Courses which already contain an [Archiv]
   in the title will not be updated.
   
*/


// ref_id of the archive categories
$archive_categories = array(44,54);

// postfix for archived courses (default: none)
// if you uncomment the line the string will be added to the [Archiv] prefix, for
// example if you define "Sommersemester 2011" here the string will be
// [Archiv Sommersemester 2011]
// 
//$archive_postfix = "Sommersemester 2011";

// language for archive string
// 1 = German (Archiv), 2 = English (Archive)
$archive_lang = 1;


// database connection (ILIAS)

define("MYSQL_HOST", "localhost");
define("MYSQL_DB", "database");
define("MYSQL_USER", "username");
define("MYSQL_PASSWORD", "password");

// security hash (random string to restrict access to the script)
define("SECURITY_HASH","ab2cdbd349d115dc1f785c3b84746d96");

/***********************************************
 DON'T CHANGE ANYTHING BELOW THIS LINE 
 ***********************************************/

// build archive string
switch($archive_lang)
{
    case 2:
            $archive_prefix = "Archive";
            break;
    default:
            $archive_prefix = "Archiv";
}

$archive_string = $archive_prefix;

// add postfix if needed
if(isset($archive_postfix))
    $archive_string .= " ".$archive_postfix;


// fetch courses and check if they are in the archiv
function getCourses($debug=0)
{
        global $archive_prefix;

	if($debug==1)
	{
		echo "DEBUG MODE --- nothing will be written to the database<br /><br />\n";
	}
	
	// get courses which are not archived and not deleted
	$sql = "select od.obj_id, od.title, ref.ref_id from object_data as od join object_reference as ref on od.obj_id = ref.obj_id ".
			"where od.type = 'crs' and ref.deleted is null and od.title not like '%[".$archive_prefix."%'";
			
	$query = mysql_query($sql);
	if(mysql_error())
	{
		//"blupp1!";
		echo "Datenbank Fehler: ".mysql_error();
	}
	else
	{
		if(mysql_num_rows($query) > 0)
		{
			$found_count = 0;
			for($i=0;$i<mysql_num_rows($query);$i++)
			{
				$course = array();
				$course['ref'] = mysql_result($query,$i,"ref.ref_id");
				$course['title'] = mysql_result($query,$i,"od.title");
				$course['obj_id'] = mysql_result($query,$i,"od.obj_id");
				
				if(isInArchive($course['ref']))
				{
					if($debug==0)
					{
						updateCourse($course['obj_id'],$course['title']);
						echo "Course ID: ".$course['obj_id']." //// ".$course['title']."<br />\n";
					}
					else
					{
						echo "Course ID: ".$course['obj_id']." //// ".$course['title']."<br />\n";
					}
					$found_count++;
				}
				else
				{
					echo "Not in archive! (".$course['obj_id']." //// ".$course['title'].")<br />\n";
				}
			}
			echo "<br /><br />Found: ".$found_count;
		}
		else
		{
			echo "No Couses found!";
		}
	}
}

function updateCourse($id,$title)
{

    global $archive_string;
                   
	$sql = "update object_data set title='[".sqlClean($archive_string)."] ".sqlClean($title)."' where obj_id=".$id;
	$query = mysql_query($sql);
	if(mysql_error())
	{
		echo "Database Error: ".mysql_error();
	}

}

function isInArchive($ref,$format="title")
{
	global $archive_categories;
	
	$select_checktree = "select depth from tree where child=".sqlClean($ref)." and depth=2";
	$query_checktree = mysql_query($select_checktree);
	if(mysql_error())
	{
		echo "Database Error: ".mysql_error();
	}
	else
	{
		if(mysql_num_rows($query_checktree) == 1)
		{
			echo "Error: Already on top level!";
		}
		else
		{
			$found = false;
			$thischild = array();
			while($found == false)
			{
				$select_parent = "select tr.depth,tr.parent from tree as tr join object_reference as ref on ".
						 "ref.ref_id = tr.child where tr.child=".sqlClean($ref);
				$query_parent = mysql_query($select_parent);
				
				if(mysql_error())
				{
					echo "Database Error: ".mysql_error();
				}
				else
				{
					$thischild['depth'] = mysql_result($query_parent,0,"tr.depth");
					$thischild['parent'] = mysql_result($query_parent,0,"tr.parent");
					
					if(in_array($thischild['parent'],$archive_categories))
					{
						$found = true;
						
						if(mysql_error())
						{
							echo "Datenbank Fehler: ".mysql_error();
						}
						else
						{
							return true;
						}
					}
					else
					{
						$ref = $thischild['parent'];
					}
				}
				if($thischild['depth'] == 1)
				{
					return false;
				}
			} /* end while */		
		}		
	}	
}

//------------------------------------------------------------------
// Protect us from SQL Injections
function sqlClean($input) 
{
	$output = mysql_real_escape_string(get_magic_quotes_gpc() ? stripslashes($input) : ($input));
	return $output;
}


// Call the functions

if (!isset($_REQUEST['key'])) { echo "no key"; }
else {
		if(preg_match('/^[a-f0-9]{32}$/', $_REQUEST['key']))
		{
			if($_REQUEST['key'] == SECURITY_HASH)
			{
				// make connection - don't edit
				$link = mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD);
				mysql_select_db(MYSQL_DB,$link);
				
				if(isset($_REQUEST['debug']) && $_REQUEST['debug'] == 1)
				{
					getCourses(1);
				}
				else
				{
					getCourses();
				}
				
				mysql_close();
			}
			else
			{
				echo "wrong key!";
			}
		}
		else
		{
			echo "invalid key";
		}
}

?>