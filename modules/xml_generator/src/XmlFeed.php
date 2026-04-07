<?php
namespace app\modules\xml_generator\src;

use DateTime;
use SimpleXMLElement;
use Exception;
use app\models\Queue;
use app\models\User;
use app\modules\shopify\ProductFeed;
use app\modules\shopify\CustomerFeed;
use app\modules\shopify\OrderFeed;
use app\modules\xml_generator\helper\SambaHelper;

/**
 * Class XmlFeed
 *
 * @property int $id
 * @property int $integrated
 * @property int $to_integrate
 * @property int $next_integration_date
 * @property string $integration_type
 *
 * @package app\modules\xml_generator\src
 */
class XmlFeed implements FeedGenerator
{
    const PRODUCT            = 'product';
    const ORDER              = 'order';
    const CUSTOMER           = 'customer';
    // const CATEGORY           = 'category';
    // const TAGS               = 'tags';
    // const SUBSCRIBERS_IMPORT = 'subscribersimport';

    /**
     * @var string
     */
    private $_type;

    /**
     * @var User
     */
    protected $_user;

    /**
     * @var string
     */
    protected $_path;

    /**
     * @var string
     */
    protected $_token;

    /**
     * @var Queue
     */
    protected $_queue;

    /**
     * @param null $what
     * @return int
     *
     * @throws \Exception
     */
    public function generate($processType = null): int
    // public function generate($what = null): int
    {
        $feedClass = null;

        switch ($this->_type) {
            case self::PRODUCT:
                $feedClass = new ProductFeed();
                break;
            // case self::CATEGORY:
            //     $feedClass = new CategoryFeed();
            //     break;
            case self::ORDER:
                $feedClass = new OrderFeed();
                break;
            case self::CUSTOMER:
                $feedClass = new CustomerFeed();
                break;
            // case self::TAGS:
            //     $feedClass = new Tags();
            //     break;
            // case 'subscribers':
            //     $feedClass = new SubscribersFeed();
            //     break;
            // case 'phonesubscribers':
            //     $feedClass = new PhonesubscribersFeed();
            //     break;
            default:
                throw new Exception('Cannot create feed. Invaild feed type');
        }

        return $feedClass
            ->setType($this->_type)
            ->setUser($this->_queue->getCurrentUser())
            ->setQueue($this->_queue)
            // ->generate($what);
            ->generate($processType);
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    /**
     * Returns writable base path for feed files.
     * On Heroku (read-only filesystem) set FEEDS_PATH to a /tmp subdirectory.
     * Defaults to @runtime/feeds (runtime/ must be writable).
     */
    public static function getFeedsBasePath(): string
    {
        $env = getenv('FEEDS_PATH');
        if ($env !== false && $env !== '') {
            return rtrim($env, '/');
        }
        return \Yii::getAlias('@runtime') . '/feeds';
    }

    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        $ext = $temp ? '.xml.tmp' : '.xml';

        $base      = self::getFeedsBasePath();
        $dir       = $base . '/' . $this->_type . '/' . $this->_user->uuid;
        $file_path = $dir . '/' . $this->_type . $ext;

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($get_file_path) {
            return $file_path;
        }

        if (! is_file($file_path)) {
            if (($queue = $this->_queue) == null) {
                $queue = Queue::findLastForType($this->_type);
            }

            $minutes = $queue->getWhenFinished();

            $info_xml = new SimpleXMLElement('<INFO/>');
            $info_xml->addChild('NOTICE', "Feed is generating. Come back in $minutes minutes.");
            return $info_xml->asXML();
        }

        return file_get_contents($file_path);
    }

    /**
     * @return bool
     */
    public function isFinished()
    {
        if ($this->_queue->max_page == 0 && $this->_queue->page == 0) {
            return false;
        }

        return $this->_queue->page >= $this->_queue->max_page;
    }

    /**
     * @param User $user
     *
     * @return $this
     */
    public function setUser(User $user): XmlFeed
    {
        $this->_user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->_queue->getCurrentUser();
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type): XmlFeed
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token): XmlFeed
    {
        $this->_token = $token;

        return $this;
    }

    public function setQueue(Queue $queue): XmlFeed
    {
        $this->_queue = $queue;

        return $this;
    }

    /**
     * @param $date
     *
     * @return string
     * @throws \Exception
     */
    public function getCorrectSambaDate($date): string
    {
        return SambaHelper::getCorrectSambaDate($date);
    }

    public function getCorrectDbDate($date): string
    {
        return SambaHelper::getCorrectDbDate($date);
    }
}
