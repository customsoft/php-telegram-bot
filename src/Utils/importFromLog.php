<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Utils;

use \Longman\TelegramBot\Telegram;
use \Longman\TelegramBot\Exception\TelegramException;
use \Longman\TelegramBot\Entities\Update;

class ImportFromLog
{
    protected $telegram;

    public function initializeImport($credentials)
    {
        define('PHPUNIT_TESTSUITE', 'some value');
        $API_KEY = 'random';
        $BOT_NAME = 'bot_name';
        // Create Telegram API object
        $this->telegram = new Telegram($API_KEY, $BOT_NAME);
        $this->telegram->enableMySQL($credentials);
    }

    public function importFile($filename)
    {
        $update = null;
        try {
            foreach (new \SplFileObject($filename) as $current_line) {
                $json_decoded = json_decode($update, true);
                if (!is_null($json_decoded)) {
                    echo $update . "\n\n";
                    $update = null;
                    if (empty($json_decoded)) {
                        echo "Empty update: \n";
                        echo $update . "\n\n";
                        continue;
                    }
                    $this->telegram->processUpdate(new Update($json_decoded, 'anybot'));
                }
                $update .= $current_line;
            }
        } catch (TelegramException $e) {
            return $e;
        }
        return 1;
    }
}






//$filename='logfile.log';
//$CREDENTIALS = array('host'=>'localhost', 'user'=>'', 'password'=>'', 'database'=>'');
//
//$update = null;
//try {
//    // Create Telegram API object
//    $telegram = new Longman\TelegramBot\Telegram($API_KEY, $BOT_NAME);
//    $telegram->enableMySQL($CREDENTIALS);
//    foreach (new SplFileObject($filename) as $current_line) {
//        $json_decoded = json_decode($update, true);
//        if (!is_null($json_decoded)) {
//            echo $update . "\n\n";
//            $update = null;
//            if (empty($json_decoded)) {
//                echo "Empty update: \n";
//                echo $update . "\n\n";
//                continue;
//            }
//            $telegram->processUpdate(new Longman\TelegramBot\Entities\Update($json_decoded, $BOT_NAME));
//        }
//        $update .= $current_line;
//    }
//
//} catch (Longman\TelegramBot\Exception\TelegramException $e) {
//    // log telegram errors
//    echo $e;
//}
    



