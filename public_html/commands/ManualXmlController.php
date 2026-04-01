<?php
namespace app\commands;

use app\models\Queue;
use app\modules\xml_generator\src\XmlFeed;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\User;

class ManualXmlController extends Controller
{
    public function actionTest(){
        die ("TESCIK");
    }
    public function actionIndex(){
        die ("manual xml index");
    }
}
