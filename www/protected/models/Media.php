<?php

Yii::import('application.models._base.BaseMedia');

class Media extends BaseMedia
{
    public $tag_count; // used in search and admin view grid views.

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function rules()
    {
        return array(
            array('name, size, mime_type, created, modified', 'required'),
            array('size, locked', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 254),
            array('mime_type, batch_id', 'length', 'max' => 45),
            array('last_access', 'safe'),
            array('batch_id, last_access, locked', 'default', 'setOnEmpty' => true, 'value' => null),
            array('id, name, size, mime_type, batch_id, last_access, locked, created, modified, tag_count', 'safe', 'on' => 'search'),
            array('remote_id', 'unique', 'criteria' => array(
                'condition' => '`institution_id`=:secondKey',
                'params' => array(
                    ':secondKey' => $this->institution_id
                )
            ))
        );
    }


    /**
     * Provides a CActiveDataProvider for the media tool search functionality
     *
     * @return object CActiveDataProvider the dataprovider
     */
    public function search($tagRequired = false)
    {
        if ($tagRequired && !isset($_GET["Custom"])) {
            return new CActiveDataProvider($this, array('criteria' => array('condition' => 'name=\'impossible___request___\'',)));
        }
        $criteria = new CDbCriteria;
        $criteria->alias = 't';
        // TODO: we want to show the tag count for each media.
        // regardless if it has been tagged or not
        // used a join, group by and having but this would only show medias that had at least one tag use
        // to fix that we're now - sigh - using subselects is not the fastest way. Might need improvement in further versions
        $criteria->select = 't.*, (SELECT COUNT(tcu.tag_id) FROM tag_use tcu WHERE tcu.media_id=t.id AND tcu.weight > 0) AS tag_count';
      //  $criteria->select = array('t.*', '(SELECT COUNT(tcu.tag_id) FROM tag_use tcu WHERE tcu.media_id=t.id AND tcu.weight > 0) AS tag_count');
        $criteria->distinct = true;

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('size', $this->size);
        $criteria->compare('mime_type', $this->mime_type, true);
        $criteria->compare('batch_id', $this->batch_id, true);
        $criteria->compare('last_access', $this->last_access, true);
        $criteria->compare('locked', 1);
        $criteria->compare('created', $this->created, true);
        $criteria->compare('modified', $this->modified, true);
        $criteria->compare('institution_id', $this->institution_id);
        $pageSize = Yii::app()->fbvStorage->get("settings.pagination_size");

        if (isset($_GET["Custom"])) {
            if (isset($_GET["Custom"]["tags"])) {
                $parsed_tags = MGTags::parseTags($_GET["Custom"]["tags"]);
                $criteria->select = 't.*, (SELECT COUNT(tcu.tag_id) FROM tag_use tcu WHERE tcu.media_id=t.id AND tcu.weight > 0) AS tag_count  ,
                        (SELECT  COUNT(*) as counted
						FROM `tag_use` `tu`
						JOIN `tag` `tag` ON tu.tag_id = tag.id
						WHERE (tu.weight > 0) AND (`tag`.`tag` IN ( \'' . implode ('\', \'', $parsed_tags) . '\') AND tu.media_id = t.id)
						GROUP BY `tu`.`media_id`
						ORDER BY `counted` DESC) AS relevance_count';

                if (count($parsed_tags) > 0) {
                    $cmd = Yii::app()->db->createCommand();
                    $criteria->join .= "  LEFT JOIN {{tag_use}} tagu ON tagu.media_id=t.id
                              LEFT JOIN {{tag}} tg ON tg.id=tagu.tag_id";
                    $criteria->group = "tagu.media_id";
                    //$criteria->order = ('COUNT(*) ASC');
                    $tags = null;
                    if ($_GET["Custom"]["tags_search_option"] == "OR") {
                        $tags = $cmd->selectDistinct('tu.media_id')
                            ->from('{{tag_use}} tu')
                            ->join('{{tag}} tag', 'tu.tag_id = tag.id')
                            ->where(array('and', 'tu.weight > 0', array('in', 'tag.tag', array_values($parsed_tags))))
                            ->queryAll();
                    } else {
                        $tags = $cmd->selectDistinct('tu.media_id, COUNT(DISTINCT tu.tag_id) as counted')
                            ->from('{{tag_use}} tu')
                            ->join('{{tag}} tag', 'tu.tag_id = tag.id')
                            ->where(array('and', 'tu.weight > 0', array('in', 'tag.tag', array_values($parsed_tags))))
                            ->group('tu.media_id')
                            ->having('counted = :counted', array(':counted' => count($parsed_tags)))
                            ->queryAll();
                    }

                    if ($tags) {
                        $ids = array();
                        foreach ($tags as $tag) {
                            $ids[] = $tag["media_id"];
                        }
                        $criteria->addInCondition('t.id', array_values($ids));
                    } else {
                        $criteria->addInCondition('t.id', array(0));
                    }
                }
            }

            if(isset($_GET['Custom']['items_per_page']))
            {
                $pageSize = $_GET['Custom']['items_per_page'];
            }
            if(isset($_GET['Custom']['type_sort']))
            {
                $typeSort = $_GET['Custom']['type_sort'];
                if($typeSort == 'a_z') $criteria->order = 'name ASC';
                if($typeSort == 'z_a') $criteria->order = 'name DESC';
                if($typeSort == 'relevance') $criteria->order = 'relevance_count DESC, tag_count DESC';
            }
            $criteria->join .= ' LEFT JOIN {{institution}} inst ON inst.id=t.institution_id';
            $criteria->addInCondition('inst.status', array(1));
            if (isset($_GET["Custom"]["institutions"]) && is_array($_GET["Custom"]["institutions"])) {
                $criteria->addInCondition('inst.id', array_values($_GET["Custom"]["institutions"]));
            }

            if (isset($_GET["Custom"]["collections"]) && is_array($_GET["Custom"]["collections"])) {
                $criteria->join .= ' LEFT JOIN {{collection_to_media}} isi ON isi.media_id=t.id';
                $criteria->addInCondition('isi.collection_id', array_values($_GET["Custom"]["collections"]));
            }

            if (isset($_GET["Custom"]["media_types"]) && is_array($_GET["Custom"]["media_types"])) {
                $criteria->addInCondition('LEFT(t.mime_type, 5)', array_values($_GET["Custom"]["media_types"]));
            }

            if (isset($_GET["Custom"]["username"]) && trim($_GET["Custom"]["username"]) != "") {
                $criteria->distinct = true;

                $criteria->join .= "  LEFT JOIN {{tag_use}} tu ON tu.media_id=t.id
                              LEFT JOIN {{game_submission}} gs ON gs.id=tu.game_submission_id
                              LEFT JOIN {{session}} s ON s.id=gs.session_id
                              LEFT JOIN {{user}} u ON u.id=s.user_id";

                $criteria->addSearchCondition('u.username', $_GET["Custom"]["username"]);
            }
        }

        if (isset($_GET['Media']['tag_count'])) {

            // as YII does not support a $criteria->compare on a HAVING clause
            // we have to extract magic helpers hourselves

            $value = (string)$_GET['Media']['tag_count'];

            if (preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)) {
                $value = $matches[2];
                $op = $matches[1];
            } else {
                $op = '';
            }

            if ($value !== '') {
                if ($op === '')
                    $op = '=';

                //TODO: fix for use of subselect in media with tags filter
                $criteria->condition .= " AND (SELECT COUNT(tcu.tag_id) FROM tag_use tcu WHERE tcu.media_id=t.id AND tcu.weight > 0) $op :tc";
                $criteria->params[':tc'] = $value;

            }

        }

        // if (!Yii::app()->request->isAjaxRequest)
           // $criteria->order = 'tag_count DESC';

        $sort = new CSort;
        $sort->attributes = array(
            'tag_count' => array(
                'asc' => 'tag_count',
                'desc' => 'tag_count DESC',
            ),
            '*',
        );

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $pageSize,
            ),
            'sort' => $sort,
        ));
    }

    protected function getLicenceInfo($id)
    {
        $data = Yii::app()->db->createCommand()
            ->selectDistinct('l.id, l.name, l.description')
            ->from('{{licence}} l')
            ->where('l.id=:id', array(':id' => $id))
            ->queryAll();

        $licence = new GameLicenceDTO();
        $licence->id = $data[0]['id'];
        $licence->name = $data[0]['name'];
        $licence->description = $data[0]['description'];
        return $licence;
    }

    /**
     * Provides a CActiveDataProvider. Lists all medias that are not processed via the import tool
     *
     * @return object CActiveDataProvider the dataprovider for the import process screen
     */
    public function unprocessed()
    {
        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('size', $this->size);
        $criteria->compare('mime_type', $this->mime_type, true);
        $criteria->compare('batch_id', $this->batch_id, true);
        $criteria->compare('last_access', $this->last_access, true);
        $criteria->compare('locked', 0);
        $criteria->compare('created', $this->created, true);
        $criteria->compare('modified', $this->modified, true);

        if (!Yii::app()->request->isAjaxRequest)
            $criteria->order = 'name ASC';

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => Yii::app()->fbvStorage->get("settings.pagination_size") * 2,
            ),
        ));
    }

    /**
     * As medias have got files we have to make sure that all files are removed from the file system once an media
     * has been deleted.
     *
     * This method is automatically called as Yii behaviour
     */
    public function afterDelete()
    {
        $path = realpath(Yii::app()->getBasePath() . Yii::app()->fbvStorage->get("settings.app_upload_path"));
        $path_parts = pathinfo($this->name);

        //remove file from .../uploads/medias
        if (file_exists($path . "/images/" . $this->name) && is_writable($path . "/images/" . $this->name))
            unlink($path . "/images/" . $this->name);

        //remove file from .../uploads/medias
        if (file_exists($path . "/thumbs/" . $this->name) && is_writable($path . "/thumbs/" . $this->name))
            unlink($path . "/thumbs/" . $this->name);

        //remove all scaled versions
        $files = glob($path . "/scaled/" . $path_parts["filename"] . ".mg-scaled.*");
        if (is_array($files) && count($files) > 0) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        parent::afterDelete();
    }

    /**
     * Created the CArrayDataProvider for the media listing on a user/player detail view.
     *
     * @param int $user_id the user_id of the user for which the medias should be listed
     * @return object CArrayDataProvider the configured dataprovider that can list all medias that are tag by the given user
     */
    public function searchUserMedias($user_id)
    {
        $command = Yii::app()->db->createCommand()
            ->select('COUNT(i.id) as counted, COUNT(DISTINCT tu.tag_id) as tag_counted, i.id, i.name,inst.url')
            ->from('{{session}} s')
            ->join('{{game_submission}} gs', 'gs.session_id=s.id')
            ->join('{{tag_use}} tu', 'tu.game_submission_id = gs.id')
            ->join('{{media}} i', 'i.id = tu.media_id')
            ->join('{{institution}} inst', 'inst.id = i.institution_id')
            ->where(array('and', 'tu.weight > 0', 's.user_id=:userID'), array(":userID" => $user_id))
            ->group('i.id, i.name')
            ->order('gs.created DESC');
        $command->distinct = true;
        $tags = $command->queryAll();
        return new CArrayDataProvider($tags, array(
            'id' => 'id',
            'sort' => array(
                'attributes' => array(
                    'id', 'name', 'counted'
                ),
            ),
            'pagination' => array(
                'pageSize' => Yii::app()->fbvStorage->get("settings.pagination_size")
            ),
        ));
    }

    /**
     * Created the CArrayDataProvider for the media listing on a tag detail view.
     *
     * @param int $tag_id the tag_id of the tag for which the medias should be listed
     * @return object CArrayDataProvider the configured dataprovider that can list all medias that are tag with the identified tag
     */
    public function searchTagMedias($tag_id)
    {
        $command = Yii::app()->db->createCommand()
            ->select('COUNT(i.id) as counted, COUNT(DISTINCT s.user_id) as user_counted, i.id, i.name,i.mime_type,inst.url')
            ->from('{{session}} s')
            ->join('{{game_submission}} gs', 'gs.session_id=s.id')
            ->join('{{tag_use}} tu', 'tu.game_submission_id = gs.id')
            ->join('{{media}} i', 'i.id = tu.media_id')
            ->leftJoin('{{institution}} inst', 'inst.id=i.institution_id')
            ->where(array('and', 'tu.weight > 0', 'tu.tag_id=:tagID'), array(":tagID" => $tag_id))
            ->group('i.id, i.name')
            ->order('gs.created DESC');
        $command->distinct = true;
        $tags = $command->queryAll();
        return new CArrayDataProvider($tags, array(
            'id' => 'id',
            'sort' => array(
                'attributes' => array(
                    'id', 'name', 'counted', 'mime_type', 'url'
                ),
            ),
            'pagination' => array(
                'pageSize' => Yii::app()->fbvStorage->get("settings.pagination_size")
            ),
        ));
    }

    /**
     * returns a comma separated list of the tag that are used most for the media. each of the listed tags
     * will be linked to its view page. in addition the use count will be given.
     *
     * @param int $num_tags the number of top tags to be listed
     */
    public function getTopTags($num_tags = 10)
    {
        $tags = Yii::app()->db->createCommand()
            ->select('count(t.id) as counted, t.id, t.tag')
            ->from('{{tag_use}} tu')
            ->join('{{tag}} t', 'tu.tag_id = t.id')
            ->where(array('and', 'tu.weight > 0', 'tu.media_id=:mediaID'), array(":mediaID" => $this->id))
            ->group('t.id, t.tag')
            ->order('counted DESC')
            ->limit($num_tags)
            ->queryAll();

        if ($tags) {
            $out = array();
            foreach ($tags as $tag) {
                $linkEdit = CHtml::link($tag["tag"] . '(' . $tag["counted"] . ')', array("/admin/tag/view", "id" => $tag["id"]), array('class' => 'edit ir'));
                $linkView = CHtml::link($tag["tag"] . '(' . $tag["counted"] . ')', array("/admin/tag/view", "id" => $tag["id"]), array('class' => 'tag'));
                $out[] = '<div class="tag-dialog">' . $linkEdit . $linkView . '</div>';
            }
            return implode("", $out);
        } else {
            return "";
        }
    }

    /**
     * Lists all media set of the media as comma separated list of html links (linking to the collection/view page)
     *
     * @return string Partial html (list of links to media sets of the media)
     */
    public function listCollections()
    {
        $out = array();
        if (count($this->collections) > 0) {
            foreach ($this->collections as $collection) {
                $out[] = GxHtml::link(GxHtml::encode($collection->name), array('collection/view', 'id' => $collection->id));
            }
        }
        return implode(", ", $out);
    }

    public function listCollectionsText()
    {
        $out = array();
        if (count($this->collections) > 0) {
            foreach ($this->collections as $collection) {
                if($collection->name == 'All') continue;
                $out[] = (GxHtml::encode($collection->name));
            }
        }
        return implode(", ", $out);
    }

    /**
     * Updates the last_access time of each media identified by the ids in the passed array.
     *
     * @param array $media_ids array of integer - the ids of the medias which last_access should be set to now
     */
    public function setLastAccess($media_ids)
    {
        if (is_array($media_ids) && count($media_ids)) {
            $sql = "  UPDATE media
                SET last_access=now()
                WHERE id IN (" . implode(",", $media_ids) . ")";

            $command = Yii::app()->db->createCommand($sql);
            $command->execute();
        }
    }

}
