<?php

function task_multistoragebackup($task)
{
	global $db, $mybb, $config, $lang, $plugins;
	static $contents;

	@set_time_limit(0);

	if(!defined('MYBB_ADMIN_DIR'))
	{
		if(!isset($config['admin_dir']))
		{
			$config['admin_dir'] = "admin";
		}

		define('MYBB_ADMIN_DIR', MYBB_ROOT.$config['admin_dir'].'/');
	}

	// Check if folder is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		add_task_log($task, $lang->task_backup_cannot_write_backup);
	}
	else
	{
		$db->set_table_prefix('');

		// SDS - Create different file variables
		$filename = 'backup_'.date("_Ymd_His_").random_str(16);
		$file = MYBB_ADMIN_DIR.'backups/'.$filename;
		$fileIncomplete = $file.'.incomplete';
		$fileExtension = '.sql';

		if(function_exists('gzopen'))
		{
			// SDS - Add extension		
			$fileExtension = $fileExtension.'.gz';
		
			$fp = gzopen($fileIncomplete.$fileExtension, 'w9');
		}
		else
		{
			$fp = fopen($fileIncomplete.$fileExtension, 'w');
		}

		$tables = $db->list_tables($config['database']['database'], $config['database']['table_prefix']);

		$time = date('dS F Y \a\t H:i', TIME_NOW);
		$contents = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";

		if(is_object($plugins))
		{
			$args = array(
				'task' =>  &$task,
				'tables' =>  &$tables,
			);
			$plugins->run_hooks('task_backupdb', $args);
		}

		foreach($tables as $table)
		{
			$field_list = array();
			$fields_array = $db->show_fields_from($table);
			foreach($fields_array as $field)
			{
				$field_list[] = $field['Field'];
			}

			$fields = "`".implode("`,`", $field_list)."`";

			$structure = $db->show_create_table($table).";\n";
			$contents .= $structure;
			clear_overflow($fp, $contents);

			if($db->engine == 'mysqli')
			{
				$query = mysqli_query($db->read_link, "SELECT * FROM {$db->table_prefix}{$table}", MYSQLI_USE_RESULT);
			}
			else
			{
				$query = $db->simple_select($table);
			}

			while($row = $db->fetch_array($query))
			{
				$insert = "INSERT INTO {$table} ($fields) VALUES (";
				$comma = '';
				foreach($field_list as $field)
				{
					if(!isset($row[$field]) || is_null($row[$field]))
					{
						$insert .= $comma."NULL";
					}
					else if($db->engine == 'mysqli')
					{
						$insert .= $comma."'".mysqli_real_escape_string($db->read_link, $row[$field])."'";
					}
					else
					{
						$insert .= $comma."'".$db->escape_string($row[$field])."'";
					}
					$comma = ',';
				}
				$insert .= ");\n";
				$contents .= $insert;
				clear_overflow($fp, $contents);
			}
			$db->free_result($query);
		}

		$db->set_table_prefix(TABLE_PREFIX);

		if(function_exists('gzopen'))
		{
			gzwrite($fp, $contents);
			gzclose($fp);
			
		}
		else
		{
			fwrite($fp, $contents);
			fclose($fp);
		}
		
		// Rename the file
		rename($fileIncomplete.$fileExtension, $file.$fileExtension);
		
		
		// Custom SDS	
		
		$ftp_result = 0;
		$ftp_error_details = "";
		
		// Do we want to backup the file towards FTP
		if( $mybb->settings['multistoragebackup_ftp'] == 1 )
		{

			// Assume we fail	
			$ftp_result = 4;
		
			// Check if the function exists
			if ( function_exists( "ftp_connect" ) )
			{
		
			// Grab specific settings
			$hostArr = explode(":",$mybb->settings['multistoragebackup_ftphost']);
			
			// Host Address
			$host = $hostArr[0];
			
			// Host Port
			$port = $hostArr[1];
			
			// Check port 
			if($port == "")
			{
				// Set default FTP port
				$port = 21;
			}
	
			// Add log
			// add_task_log($task, "Backing up the file '".$sourceFile."'towards FTP destination '".$host.":".$port."'");
		
			// Open FTP connection
			$ftp = ftp_connect($host,$port);
			
			// When we have a connection
			if($ftp)
			{
			
				// Try to log on
				if(ftp_login($ftp, $mybb->settings['multistoragebackup_ftpuser'],$mybb->settings['multistoragebackup_ftppass']))
				{

					// Source File name
					$filesource = $file.$fileExtension;
				
					// Destination file name
					$ftpfilename = $filename.$fileExtension;
					
					// Do we want a specific name prefix
					if (!empty($mybb->settings['multistoragebackup_ftpfilename_prefix']))
					{
					
						// Set the prefix
						$ftpfilename = $mybb->settings['multistoragebackup_ftpfilename_prefix']."_".$ftpfilename;					
									
					}
					
					// Determine the starting path based on our configuartion settings
					$ftpstartpath = includeTrailingCharacter($mybb->settings['multistoragebackup_ftppath'],"/");
					
					// Do we want to use year-month subfolders
					if ( $mybb->settings['multistoragebackup_ftpdirym'] == 1 ) 
					{
						
						// Get current time
						$date_now = new DateTime();
						
						// Determine the directory
						$month_dir = $date_now->format("Ym");
						
						// Determine our work path
						$ftpstartpathym = $ftpstartpath.$month_dir;
						
						// Let's try to change dir, if it fails then lets' try to create it
						if (!@ftp_chdir($ftp, $ftpstartpathym ))
						{						
							// Let's try to create it
							if (@ftp_mkdir($ftp, $ftpstartpathym ))
							{
						 		// Make sure we use the new diretory	
						 		$ftpstartpath = includeTrailingCharacter($ftpstartpathym,"/");
						 	}
								
						}
						else
						{
							// Make sure we use the existing subfolder
					 		$ftpstartpath = includeTrailingCharacter($ftpstartpathym,"/");
						
						}
						
						// Change back to the home folder
						@ftp_chdir($ftp, "/" );
											
					}	
											
					// Complete the file ftp name
					$ftpcompletefilename = $ftpstartpath.$ftpfilename;																						
	
					// Try to put the file 	
					if(ftp_put($ftp, $ftpcompletefilename, $filesource, FTP_BINARY))
					{					
					
						// Set we are fine
						$ftp_result = 1;
					}
					else
					{
					
						// Log error
						$ftp_error_details = "Unable to put the backup file '".$filesource."' on the FTP server location as file '".$ftpcompletefilename."'";					
		
					}
					
					// Close the connection
					ftp_close($ftp);
				}
				else
				{
		
					// Log error
					$ftp_error_details = "Unable to log on with the provided user credentials";
		
				}
			}
			else
			{
				// Log error
				$ftp_error_details = "Unable to connect towards the FTP server";
			}
			
			}
			else
			{
			
				// Functionality not available
				$ftp_error_details = "FTP libary functions not available";
			}
		}		
		
		// Add task log
		//add_task_log($task, $lang->task_backup_ran);
		
		// Determine final task log entry
		$logText = "v21 Multi-storage backup executed successfully - results: ";
		$logFinalText = "";
		
		// If we are NOT using FTP
		if ( $ftp_result == 0 ) 		
		{
		
			// Add text
			$logFinalText = $logText."stored file successfully in backup folder";
		
		}
		else 
		{
			// Add text
			$logText = $logText."stored file successfully in backup folder / ";
		
			if ( $ftp_result == 1 )
			{

				// Add text
				$logFinalText = $logText."send file successfully to FTP location";
		
			}			
			else
			{

				// Add text
				$logFinalText = $logText."cound NOT send file to FTP location - error details '".$ftp_error_details."'";
		
			}
		}
		
		// Add our final task log 
		add_task_log($task, $logFinalText);
	}
}

// Allows us to refresh cache to prevent over flowing
function clear_overflow($fp, &$contents)
{
	global $mybb;

	if(function_exists('gzopen'))
	{
		gzwrite($fp, $contents);
	}
	else
	{
		fwrite($fp, $contents);
	}

	$contents = '';
}

function log_ftp_info($task, $text) {

	// Add log
	add_task_log($task, $text);

}

function log_ftp_error($task, $text, $isFatal = True ) {

	// Add log
	add_task_log($task, "ERROR - ".$text);

	// When we are fatal
	if ( $isFatal == True ) 
	{	
		// Add log
		add_task_log($task, "! Backup towards FTP is canceled !");
	}

}

function includeTrailingCharacter($string, $character)
{
    if (strlen($string) > 0) {
        if (substr($string, -1) !== $character) {
            return $string . $character;
        } else {
            return $string;
        }
    } else {
        return $character;
    }
}

?>