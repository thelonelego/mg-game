<?php

/**
 * This is the base implementation of a dictionary plug-in 
 */

class MGDictionaryPlugin extends MGPlugin {  
  function init() {
    parent::init();
  }
  
  /**
   * This handler allows dictionary plugins to contribute to the game submissions parsing
   * 
   * @param object $game the game object
   * @param object $game_model the active model of the current game
   * @return boolean true if parsing was successful
   */
  function parseSubmission(&$game, &$game_model) {
    return true;
  }
  
  /**
   * This is a callup that will allow you to check wether a list of tags are listed in the 
   * dictionary 
   * 
   * array(
   *  'tag1', true/false // true if found
   *  'tag2', true/false // true if found
   *   ...
   * )
   * 
   * @param object $game The game object
   * @param object $game_model The game model
   * @param array $tags the tags to be looked up as a single dimension array array('tag1', 'tag2', ...)
   * @param array $user_ids optional user_ids that might influence the lookup
   * @param array $image_ids optional image_ids that might influence the lookup
   * @return array the checked tags
   */
  function lookup(&$game, &$game_model, $tags, $user_id=null, $image_ids=null) {
    return array();
  }
  
  /**
   * With help of this method you can influence the weight of tag submitted by the players. The weightened 
   * tags will be used for scoring and saved as tag uses into the database
   * 
   * @param object $game The game object
   * @param object $game_model The game model
   * @param array $tags the tags to be looked up as a single dimension array array('tag1', 'tag2', ...)
   */
  function setWeights(&$game, &$game_model, $tags) {
    return $tags;
  }
  
  /**
   * With help of this method you can add further elements to the words to avoid array
   * returned the player in the turn's data.
   * 
   * See MGTags::saveTags for the data structure of the $tags array
   * 
   * The format of &$wordsToAvoid is 
   * array(
   *  image_id = array(
   *    tag_id => array(
   *      "tag" => "tag" // tag == the word to avoid
   *      "total" => "SUM(tu.weight)" // this is just additional info provided by MGTags::getTagsByWeightThreshold(...)
   *    )
   *    ...
   *  )
   *  ...
   * )
   * @param array $wordsToAvoid the words to avoid generated by MGTags::getTagsByWeightThreshold(...)
   * @param array $used_images array of image_ids that will be used in this turn 
   * @param object $game the object representing the current game 
   * @param object $game_model the current games model
   * @param array $tags the previous turn's submitted tags
   */
  function wordsToAvoid(&$wordsToAvoid, &$used_images, &$game, &$game_model, &$tags) {}
  
  /**
   * This function allows to add a tag to the dictionary. The $info provided can help a plugin to 
   * filter add requests.
   * 
   * @param string $tag the tag to be stored
   * @param string $info a short info about the tag.
   * return boolean true if the tag has been successfully stored
   */
  function add($tag, $info) {}
  
  /**
   * Placeholder for future functionality
   * 
   * Not used in V.1.0
   */
  function cleanUp() {}
  
  /**
   * Placeholder for future functionality
   * 
   * Not used in V.1.0
   */
  function expand() {} 
  
}
