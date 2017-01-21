<?php

namespace Drupal\lingotek\Form;

require_once 'StandardImportObject.php';

/**
 *
 */
class FormatConverter
{
  /**
  *The document that is used to determine information about the import. It is
  *the source document that can be accessed.
  *@var object
  */
  private $source_doc;
  /**
  *The content is the bulk of the information without format, source, etc. This
  *is what is converted into the title and body of the wp import.
  *@var string
  */
  private $content;
  /**
  *The format is the format of the source upload document so that it can be handled
  *by the right conversion function (e.g. JSON, HTML, TXT, etc.)
  *@var string
  */
  private $format;

  /**
  *Constructor
  *Sets up the FormatConverter object so it can be converted
  *
  *@author Unkown
  *
  *@param object $source_doc The document that needs to be converted and then imported
  *@param string $content The content portion of the document -- This is what becomes
  *  the body of the WP post/page
  *@param string $format The format of the file. The extension of the document
  *  is used to choose the right function to convert to standard
  *@return void This builds an object that is then converted to wp import
  */
  function __construct($source_doc, $content, $format)
  {
      $this->source_doc = $source_doc;
      $this->content = $content;
      $this->format = $format;
  }

  /**
    *This function determines which converter function is needed. If there is not
    *a converter function for the selected document then it defaults to
    *JSON (in the else statement) and attempts the conversion with the assumption
    *of working with a JSON file.
    *
    *@author Unkown
    *@return StandardImportObject $importObject An object that can be imported into WP
    */
  public function convert_to_standard(){
      if (method_exists($this, $this->format.'_to_standard')){
          $importObject = call_user_func(array($this, $this->format.'_to_standard'), $this->source_doc, $this->content);
          return $importObject;
      }
      else {
          $importObject = call_user_func(array($this,'json_to_standard'), $this->source_doc, $this->content);
          return $importObject;
        }
  }

  /**
    *Converts source JSON into a StandardImportObject that can be imported into WP
    *@author Unkown
    *@param object $source_doc source document
    *@param string $content JSON string that is then parsed to get the relevant
    *information for the title and body
    *@return StandardImportObject That can be imported into WP
    */
  public function json_to_standard($source_doc, $content){

    /**
    *These string replaces are to make it so that we can access the json objects
    *with these titles. They were being imported with @ before the key and that
    *made them inaccessible. This is specific to Middleware json strings.
    *@author Matt Smith and TJ Murphy
    */
    $content = str_replace('"@title"','"title"',$content);
    $content = str_replace('"@type"','"type"',$content);
    $decoded_json = json_decode($content);
    $content = t('We could not parse the data from this document. Are you sure that it is in a recognizable format, such as JSON, XML, HTML, or plain text?');
    $title = t('No title found');
    $error = false;

    /**
    *An if statement to check for the different json patterns to find the content
    *that will be the body of the WP imported post/page
    *@author Unkown and TJ Murphy
    *@return string $content is set -- if nothing matches then $content remains
    *with a warning
    *@see $content
    */
		if (isset($decoded_json->post->post_content)){ // Drupal 7
			$content = $decoded_json->post->post_content;
		}
		else if (isset($decoded_json->body[0]->value)){ // Drupal 8
			$content = $decoded_json->body[0]->value;
		}
		else if (isset($decoded_json->args->description)){
			$content = $decoded_json->args->description;
		}
		else if (isset($decoded_json->content->body)){ // Middleware - MindTouch
			$content = $decoded_json->content->body;
		}
		else if (isset($decoded_json->body)){ // Middleware - Zendesk and HubSpot
			$content = $decoded_json->body;
		}
		else if (isset($decoded_json->email_body)){ // Middleware - Email HubSpot
			$content = $decoded_json->email_body;
		}
		else if (isset($decoded_json->post_summary)){ // Middleware
			$content = $decoded_json->post_summary;
		}
		else if (isset($decoded_json->description)){ // Middleware
			$content = $decoded_json->description;
		}

    /**
    *An if statement to check for the different json patterns to find the title
    *that will be the title of the WP imported post/page
    *@author Unkown and TJ Murphy
    *@return string $title is set -- if nothing matches then $error is set to TRUE
    *@see $title
    */
		if (isset($decoded_json->title[0]->value)){ // Drupal 8
			$title = $decoded_json->title[0]->value;
		}
		else if (isset($decoded_json->post->post_title)){ // Drupal 7
			$title = $decoded_json->post->post_title;
		}
		else if (isset($decoded_json->content->title)){ // Middleware - MindTouch
			$title = $decoded_json->content->title;
		}
		else if (isset($decoded_json->title)){ // Middleware - Zendesk and HubSpot
			$title = $decoded_json->title;
		}
		else if (isset($decoded_json->name)){ // Middleware - Email HubSpot
			$title = $decoded_json->name;
		}
		else {
			$error = true;
		}

		return new StandardImportObject($title, $content, $error);
	 }

  /**
  *Converts source XML into a StandardImportObject that can be imported into WP
  *@author Unkown
  *@param object $source_doc source document
  *@param string $xml_string XML string that is then parsed to get the relevant
  *information for the title and body
  *@return StandardImportObject That can be imported into WP
  */
  public function xml_to_standard($doc, $xml_string){
	$content = t('We could not parse the data from this document.
      Are you sure that it is in a recognizable format, such as JSON, XML, HTML, or plain text?');
	$title = t('No title found');
	$xml = new \SimpleXMLElement($xml_string);
	if ($xml === false){
		echo t("Failed to load XML");
	}

	$found_content = (array) $xml->xpath('//element');
  if ($found_content){
    $content = (string) $found_content[0];
    $title = (string) $found_content[1];
  }
  else {
    $error = true;
  }

  return new StandardImportObject($title, $content, $error);
	}

  /**
  *Converts source TXT into a StandardImportObject that can be imported into WP
  *@author Unkown
  *@param object $source_doc source document
  *@param string $content TXT string that is then parsed to get the relevant
  *information for the title and body
  *@return StandardImportObject That can be imported into WP
  */
  public function txt_to_standard($doc, $content){
	$title = $doc->properties->title;
	return new StandardImportObject($title, $content);
	}

  /**
  *Converts source PLAINTEXT into a StandardImportObject that can be imported into WP
  *@author Unkown
  *@param object $source_doc source document
  *@param string $content PLAINTEXT string that is then parsed to get the relevant
  *information for the title and body
  *@return StandardImportObject That can be imported into WP
  */
  public function plaintext_to_standard($doc, $content){
    $title = $doc->properties->title;
    return new StandardImportObject($title, $content);
  }

  /**
  *Converts source HTML into a StandardImportObject that can be imported into WP
  *@author Unkown
  *@param object $source_doc source document
  *@param string $content HTML string that is then parsed to get the relevant
  *information for the title and body
  *@return StandardImportObject That can be imported into WP
  */
  public function html_to_standard($doc, $content){
    $title = $doc->properties->title;
    return new StandardImportObject($title, $content);
  }
}
 ?>
