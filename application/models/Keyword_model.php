<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Keyword_model extends MY_Model {

    public function get_applied($list_keywords)
    {
    	$keyword_array = explode(',', $list_keywords);
    	return $this->db->where_in($this->primary_key, $keyword_array)->get($this->_table)->result();


    }
    
 /**   
    	public function get_all_keywords_used_in_projects()
    	{
    		error_log("In get_all_ keywords_used_in_project");
    		$sql = 'SELECT DISTINCT k.value
		FROM keywords k
		JOIN project_keywords pk
		ON k.id = pk.keyword_id';
		
		$query = $this->db->query($sql);
		return $query->result_array();
    	}
    	
  **/  	
    	
    	public function autocomplete($term)
	{
		// Paths for file operations with CodeIgniter helper are relative
		// to location of index.php
		$CACHE_DIR = '../application/cache/';
		$KEYWORDS_CACHE_FILENAME = 'keywords_cache.txt';
		$MAX_RETURN_VALUES = 200;
		$keywords_cache_file_exists = false;
		$keywords_cache_is_fresh = false;
		$keywords_cache_refresh_interval_seconds = 300;	
		$json_encoded_keywords_cache_as_read_from_file = '';
		
		$this->load->helper('file');
		
		// Does keywords cache file yet exist?
		$files_in_cache_dir = get_dir_file_info($CACHE_DIR);		
		if (array_key_exists($KEYWORDS_CACHE_FILENAME, $files_in_cache_dir)) 		
		{
			$keywords_cache_file_exists = true;
		}
		
		// Is the existing cache fresh?
		if ($keywords_cache_file_exists) 
		{
			$cache_date = get_file_info($CACHE_DIR . $KEYWORDS_CACHE_FILENAME, 'date');
			$timestamp_of_current_cache_file = $cache_date["date"];
			$cache_age = time() - $timestamp_of_current_cache_file;
			if ($cache_age < $keywords_cache_refresh_interval_seconds) 
			{
				$keywords_cache_is_fresh = true;
			}				
		}

		// Write a new keywords cache if none currently exists, 
		// or if the existing cache needs to be refreshed 
		if (!$keywords_cache_file_exists or !$keywords_cache_is_fresh)
		{
			$sql = 'SELECT DISTINCT k.value
			FROM keywords k
			JOIN project_keywords pk
			ON k.id = pk.keyword_id
			ORDER BY value ASC';
			$query = $this->db->query($sql);
			$data_for_keywords_cache = $query->result_array();
			write_file($CACHE_DIR . $KEYWORDS_CACHE_FILENAME, json_encode($data_for_keywords_cache));			
		}
		
		$cached_result_array = array();
		
		// Read the existing cache file if it is fresh
		
		if ($keywords_cache_file_exists and $keywords_cache_is_fresh) 
		{
			$json_encoded_keywords_cache = read_file($CACHE_DIR . $KEYWORDS_CACHE_FILENAME);
			$associative = true;
			$keywords_cache_array = json_decode($json_encoded_keywords_cache, $associative);
			
			foreach($keywords_cache_array as $row => $inner_array)
			{
  				foreach($inner_array as $inner_row => $value)
  				{
    					// Does the term match the start of a keyword in use?
    					// Our match is case-insensitive.
    					if ( stripos($value, $term)  === 0 )
					{						
						$new_element = ["value" => $value];
						array_push($cached_result_array, $new_element);

					}
  				}
  				$array_size = count ($cached_result_array);
				if ( count ($cached_result_array) >= $MAX_RETURN_VALUES) 
				{
					break;
				}
			}
			
			// If we have at least one good match above, and if we have not yet exceeded 
			// the number of items we can show in our dropdown list, show some additional partial matches
			
			if (( count ($cached_result_array) <= $MAX_RETURN_VALUES) and (count ($cached_result_array) > 0))
			{
				$divider = "=== some other keywords containing '" . $term . "' ===";
				$new_element = ["value" => $divider];
				array_push($cached_result_array, $new_element);
			
				foreach($keywords_cache_array as $row => $inner_array)
				{
  					foreach($inner_array as $inner_row => $value)
  					{
    						// Does the term occur in a keyword in use, but not at its start?
    						// Our match is case-insensitive.
    						if ( (stripos($value, $term)  !== 0 ) and ((stripos($value, $term))) )
						{						
							$new_element = ["value" => $value];
							array_push($cached_result_array, $new_element);
						}
  					}
  					$array_size = count ($cached_result_array);
					if ( count ($cached_result_array) >= $MAX_RETURN_VALUES) 
					{
						break;
					}
				}
			}			
		}
		
		return $cached_result_array;
		
/**
		// The following code achieves a result similar to the cache-based system
		// above, but without case-insensitive matching. The argument for favouring
		// a cache-based system over the one below is that the one below makes heavier
		// demands on our database.
		
		// Leaving the code below here for the present to facilitate easy comparison
		// of the two approaches.
		
		// Escaping -- https://www.codeigniter.com/userguide3/database/queries.html#escaping-queries
		$escaped_term = $this->db->escape_like_str($term);
		
		// For extra safety, parameterise the query as well
		
		$params = [];
		array_push($params, $escaped_term . "%");
		array_push($params, $escaped_term);
		array_push($params, "%" . $escaped_term . "%");
		array_push($params, $escaped_term . "%");

		$sql = 'SELECT DISTINCT k.value, "A" AS priority
		FROM keywords k
		JOIN project_keywords pk
		ON k.id = pk.keyword_id
		WHERE k.value LIKE ?  
		UNION 
		SELECT "=== some other keywords containing \"?\" ===" AS value, "B" AS priority
		UNION
		SELECT DISTINCT k.value, "C" AS priority
		FROM keywords k
		JOIN project_keywords pk
		ON k.id = pk.keyword_id
		WHERE k.value LIKE ?  
			AND k.value NOT LIKE ?  		
		ORDER BY priority ASC, value ASC
		LIMIT 200';
		
		$query = $this->db->query($sql, $params);
		return $query->result_array();
	
**/
	}

	//return comma delimited list of keywords from project
	public function create_keyword_list($project_id)
	{
		$sql = 'SELECT k.value
		FROM keywords k
		JOIN project_keywords pk ON (pk.keyword_id = k.id)
		WHERE pk.project_id = ?';

		$query = $this->db->query($sql, array($project_id));

		$keyword_list = array();

		if ($query->num_rows())
		{
			foreach ($query->result() as $keyword)
			{
				$keyword_list[] = $keyword->value;
			}	
		}
		//archive needs a ;
		return implode('; ', $keyword_list);	
	}	    

}

/* End of file keyword_model.php */
/* Location: ./application/models/keyword_model.php */
