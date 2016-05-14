<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2016 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\components;

use Yii;
use humhub\modules\notification\models\Notification;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\components\ContentAddonActiveRecord;

/**
 * Name (SocialEvent/NetworkEvent/SocialActivity/BaseEvent)
 *
 * This class represents an social activity triggered within the network.
 * An activity instance can be linked to an $originator user, which performed the activity.
 * 
 * The activity mainly provides functions for rendering the output for different channels as
 * web, mail or plain-text.
 * 
 * @since 1.1
 * @author buddha
 */
abstract class SocialActivity extends \yii\base\Component implements \yii\base\ViewContextInterface
{

    const OUTPUT_WEB = 'web';
    const OUTPUT_MAIL = 'mail';
    const OUTPUT_MAIL_PLAINTEXT = 'mail_plaintext';
    const OUTPUT_TEXT = 'text';

    /**
     * User which performed the activity.
     *
     * @var \humhub\modules\user\models\User
     */
    public $originator;

    /**
     * The source instance which created this activity
     *
     * @var \yii\db\ActiveRecord
     */
    public $source;

    /**
     * The content container this activity belongs to.
     * 
     * If the source object is a type of Content/ContentAddon or ContentContainer the container
     * will be automatically set.
     * 
     * @var ContentContainerActiveRecord
     */
    public $container = null;

    /**
     * @var string the module id which this activity belongs to (required)
     */
    public $moduleId = "";

    /**
     * The notification record this notification belongs to
     *
     * @var Notification
     */
    public $record;

    /**
     * Name of the view, used for rendering the event
     * 
     * @var string
     */
    public $viewName = null;

    /**
     * Layout file for web version
     *
     * @var string
     */
    protected $layoutWeb;

    /**
     * Layout file for mail version
     *
     * @var string
     */
    protected $layoutMail;

    /**
     * Layout file for mail plaintext version
     *
     * @var string
     */
    protected $layoutMailPlaintext;

    /**
     * Assambles all parameter required for rendering the view.
     * 
     * @return array all view parameter
     */
    protected function getViewParams($params = [])
    {
        $params['originator'] = $this->originator;
        $params['source'] = $this->source;
        $params['contentContainer'] = $this->container;
        $params['record'] = $this->record;
        $params['url'] = $this->getUrl();

        return $params;
    }

    /**
     * Renders the notification
     *
     * @return string
     */
    public function render($mode = self::OUTPUT_WEB, $params = [])
    {
        $viewFile = $this->getViewFile($mode);
        $viewParams = $this->getViewParams($params);

        $result = Yii::$app->getView()->renderFile($viewFile, $viewParams, $this);

        if ($mode == self::OUTPUT_TEXT) {
            return strip_tags($result);
        }

        $viewParams['content'] = $result;
        return Yii::$app->getView()->renderFile($this->getLayoutFile($mode), $viewParams, $this);
    }

    /**
     * Returns the correct view file 
     * 
     * @param string $mode the output mode
     * @return string the view file
     */
    protected function getViewFile($mode)
    {
        $viewFile = $this->getViewPath() . '/' . $this->viewName . '.php';
        $alternativeViewFile = "";
        
        // Lookup alternative view file based on view mode
        if ($mode == self::OUTPUT_MAIL) {
            $alternativeViewFile = $this->getViewPath() . '/mail/' . $this->viewName . '.php';
        } elseif ($mode === self::OUTPUT_MAIL_PLAINTEXT) {
            $alternativeViewFile = $this->getViewPath() . '/mail/plaintext/' . $this->viewName . '.php';
        }

        if ($alternativeViewFile != "" && file_exists($alternativeViewFile)) {
            $viewFile = $alternativeViewFile;
        }

        return $viewFile;
    }

    /**
     * Returns the layout file
     * 
     * @param string $mode the output mode
     * @return string the layout file
     */
    protected function getLayoutFile($mode)
    {
        if ($mode == self::OUTPUT_MAIL_PLAINTEXT) {
            return $this->layoutMailPlaintext;
        } elseif ($mode == self::OUTPUT_MAIL) {
            return $this->layoutMail;
        }

        return $this->layoutWeb;
    }

    /**
     * Returns the directory containing the view files for this event.
     * The default implementation returns the 'views' subdirectory under the directory containing the notification class file.
     * @return string the directory containing the view files for this notification.
     */
    public function getViewPath()
    {
        $class = new \ReflectionClass($this);
        return dirname($class->getFileName()) . DIRECTORY_SEPARATOR . 'views';
    }

    /**
     * Url of the origin of this notification
     * If source is a Content / ContentAddon / ContentContainer this will automatically generated.
     *
     * @return string
     */
    public function getUrl()
    {
        $url = '#';

        if ($this->source instanceof ContentActiveRecord || $this->source instanceof ContentAddonActiveRecord) {
            $url = $this->source->content->getUrl();
        } elseif ($this->source instanceof ContentContainerActiveRecord) {
            $url = $this->source->getUrl();
        }

        // Create absolute URL, for E-Mails
        if (substr($url, 0, 4) !== 'http') {
            $url = \yii\helpers\Url::to($url, true);
        }

        return $url;
    }

    /**
     * Build info text about a content
     *
     * This is a combination a the type of the content with a short preview
     * of it.
     *
     * @param Content $content
     * @return string
     */
    public function getContentInfo(\humhub\modules\content\interfaces\ContentTitlePreview $content)
    {
        return \yii\helpers\Html::encode($content->getContentName()) .
                ' "' .
                \humhub\widgets\RichText::widget(['text' => $content->getContentDescription(), 'minimal' => true, 'maxLength' => 60]) . '"';
    }

}
