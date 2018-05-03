<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Written by Marco Boretto <marco.bore@gmail.com>
 */

namespace Longman\TelegramBot;

use Exception;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Entities\ChosenInlineResult;
use Longman\TelegramBot\Entities\InlineQuery;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\ReplyToMessage;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;

class DB
{
    /**
     * Telegram Bot id
     *
     * @var string
     */
    protected static $bot_id;

    /**
     * MySQL credentials
     *
     * @var array
     */
    static protected $mysql_credentials = [];

    /**
     * PDO object
     *
     * @var PDO
     */
    static protected $pdo;

    /**
     * Table prefix
     *
     * @var string
     */
    static protected $table_prefix;

    /**
     * Telegram class object
     *
     * @var Telegram
     */
    static protected $telegram;

    /**
     * Initialize
     *
     * @param array    $credentials  Database connection details
     * @param Telegram $telegram     Telegram object to connect with this object
     * @param string   $table_prefix Table prefix
     * @param string   $encoding     Database character encoding
     *
     * @return PDO PDO database object
     * @throws TelegramException
     */
    public static function initialize(
    array $credentials, Telegram $telegram, $table_prefix = null, $encoding = 'utf8mb4', $bot_id = 0
    )
    {
        if (empty($credentials)) {
            throw new TelegramException('MySQL credentials not provided!');
        }

        $dsn = 'mysql:host=' . $credentials['host'] . ';dbname=' . $credentials['database'];
        $options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $encoding];
        try {
            $pdo = new PDO($dsn, $credentials['user'], $credentials['password'], $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }

        self::$pdo = $pdo;
        self::$telegram = $telegram;
        self::$mysql_credentials = $credentials;
        self::$table_prefix = $table_prefix;
        self::$bot_id = $bot_id;

        self::defineTables();

        return self::$pdo;
    }

    /**
     * External Initialize
     *
     * Let you use the class with an external already existing Pdo Mysql connection.
     *
     * @param PDO      $external_pdo_connection PDO database object
     * @param Telegram $telegram                Telegram object to connect with this object
     * @param string   $table_prefix            Table prefix
     *
     * @return PDO PDO database object
     * @throws TelegramException
     */
    public static function externalInitialize(
    $external_pdo_connection, Telegram $telegram, $table_prefix = null, $bot_id = 0
    )
    {
        if ($external_pdo_connection === null) {
            throw new TelegramException('MySQL external connection not provided!');
        }

        self::$pdo = $external_pdo_connection;
        self::$telegram = $telegram;
        self::$mysql_credentials = [];
        self::$table_prefix = $table_prefix;
        self::$bot_id = $bot_id;

        self::defineTables();

        return self::$pdo;
    }

    /**
     * Define all the tables with the proper prefix
     */
    protected static function defineTables()
    {
        $tables = [
            'callback_query',
            'chat',
            'chosen_inline_result',
            'edited_message',
            'inline_query',
            'message',
            'request_limiter',
            'telegram_update',
            'user',
            'user_chat',
        ];
        foreach ($tables as $table) {
            $table_name = 'TB_' . strtoupper($table);
            if (!defined($table_name)) {
                define($table_name, self::$table_prefix . $table);
            }
        }
    }

    /**
     * Check if database connection has been created
     *
     * @return bool
     */
    public static function isDbConnected()
    {
        return self::$pdo !== null;
    }

    /**
     * Get the PDO object of the connected database
     *
     * @return PDO
     */
    public static function getPdo()
    {
        return self::$pdo;
    }

    /**
     * Fetch update(s) from DB
     *
     * @param int    $limit Limit the number of updates to fetch
     * @param string $id    Check for unique update id
     *
     * @return array|bool Fetched data or false if not connected
     * @throws TelegramException
     */
    public static function selectTelegramUpdate($limit = null, $id = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sql = '
                SELECT `id`
                FROM `' . TB_TELEGRAM_UPDATE . '`
            ';

            if ($id !== null) {
                $sql .= ' WHERE `bot_id` = ' . self::$bot_id . ' AND `id` = :id';
            } else {
                $sql .= ' WHERE `bot_id` = ' . self::$bot_id . ' ORDER BY `id` DESC';
            }

            if ($limit !== null) {
                $sql .= ' LIMIT :limit';
            }

            $sth = self::$pdo->prepare($sql);

            if ($limit !== null) {
                $sth->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            if ($id !== null) {
                $sth->bindValue(':id', $id);
            }

            $sth->execute();

            return $sth->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Fetch message(s) from DB
     *
     * @param int $limit Limit the number of messages to fetch
     *
     * @return array|bool Fetched data or false if not connected
     * @throws TelegramException
     */
    public static function selectMessages($limit = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sql = '
                SELECT *
                FROM `' . TB_MESSAGE . '`
                WHERE `bot_id` = ' . self::$bot_id . '
                ORDER BY `id` DESC
            ';

            if ($limit !== null) {
                $sql .= ' LIMIT :limit';
            }

            $sth = self::$pdo->prepare($sql);

            if ($limit !== null) {
                $sth->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $sth->execute();

            return $sth->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Convert from unix timestamp to timestamp
     *
     * @param int $time Unix timestamp (if empty, current timestamp is used)
     *
     * @return string
     */
    protected static function getTimestamp($time = null)
    {
        return date('Y-m-d H:i:s', $time ?: time());
    }

    /**
     * Convert array of Entity items to a JSON array
     *
     * @todo Find a better way, as json_* functions are very heavy
     *
     * @param array|null $entities
     * @param mixed      $default
     *
     * @return mixed
     */
    public static function entitiesArrayToJson($entities, $default = null)
    {
        if (!is_array($entities)) {
            return $default;
        }

// Convert each Entity item into an object based on its JSON reflection
        $json_entities = array_map(function ($entity) {
            return json_decode($entity, true);
        }, $entities);

        return json_encode($json_entities);
    }

    /**
     * Insert entry to telegram_update table
     *
     * @todo Add missing values! See https://core.telegram.org/bots/api#update
     *
     * @param string $id
     * @param string $chat_id
     * @param string $message_id
     * @param string $inline_query_id
     * @param string $chosen_inline_result_id
     * @param string $callback_query_id
     * @param string $edited_message_id
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertTelegramUpdate(
    $id, $chat_id = null, $message_id = null, $inline_query_id = null, $chosen_inline_result_id = null, $callback_query_id = null, $edited_message_id = null
    )
    {
        if ($message_id === null && $inline_query_id === null && $chosen_inline_result_id === null && $callback_query_id === null && $edited_message_id === null) {
            throw new TelegramException('message_id, inline_query_id, chosen_inline_result_id, callback_query_id, edited_message_id are all null');
        }

        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_TELEGRAM_UPDATE . '`
                (`bot_id`, `id`, `chat_id`, `message_id`, `inline_query_id`, `chosen_inline_result_id`, `callback_query_id`, `edited_message_id`)
                VALUES
                (' . self::$bot_id . ', :id, :chat_id, :message_id, :inline_query_id, :chosen_inline_result_id, :callback_query_id, :edited_message_id)
            ');

            $sth->bindValue(':id', $id);
            $sth->bindValue(':chat_id', $chat_id);
            $sth->bindValue(':message_id', $message_id);
            $sth->bindValue(':edited_message_id', $edited_message_id);
            $sth->bindValue(':inline_query_id', $inline_query_id);
            $sth->bindValue(':chosen_inline_result_id', $chosen_inline_result_id);
            $sth->bindValue(':callback_query_id', $callback_query_id);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert users and save their connection to chats
     *
     * @param User   $user
     * @param string $date
     * @param Chat   $chat
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertUser(User $user, $date = null, Chat $chat = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT INTO `' . TB_USER . '`
                (`bot_id`,`id`, `is_bot`, `username`, `first_name`, `last_name`, `language_code`, `created_at`, `updated_at`)
                VALUES
                (' . self::$bot_id . ', :id, :is_bot, :username, :first_name, :last_name, :language_code, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    `is_bot`         = VALUES(`is_bot`),
                    `username`       = VALUES(`username`),
                    `first_name`     = VALUES(`first_name`),
                    `last_name`      = VALUES(`last_name`),
                    `language_code`  = VALUES(`language_code`),
                    `updated_at`     = VALUES(`updated_at`)
            ');

            $sth->bindValue(':id', $user->getId());
            $sth->bindValue(':is_bot', $user->getIsBot(), PDO::PARAM_INT);
            $sth->bindValue(':username', $user->getUsername());
            $sth->bindValue(':first_name', $user->getFirstName());
            $sth->bindValue(':last_name', $user->getLastName());
            $sth->bindValue(':language_code', $user->getLanguageCode());
            $date = $date ?: self::getTimestamp();
            $sth->bindValue(':created_at', $date);
            $sth->bindValue(':updated_at', $date);

            $status = $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }

// Also insert the relationship to the chat into the user_chat table
        if ($chat instanceof Chat) {
            try {
                $sth = self::$pdo->prepare('
                    INSERT IGNORE INTO `' . TB_USER_CHAT . '`
                    (`user_id`, `chat_id`)
                    VALUES
                    (:user_id, :chat_id)
                ');

                $sth->bindValue(':user_id', $user->getId());
                $sth->bindValue(':chat_id', $chat->getId());

                $status = $sth->execute();
            } catch (PDOException $e) {
                throw new TelegramException($e->getMessage());
            }
        }

        return $status;
    }

    /**
     * Insert chat
     *
     * @param Chat   $chat
     * @param string $date
     * @param string $migrate_to_chat_id
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertChat(Chat $chat, $date = null, $migrate_to_chat_id = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_CHAT . '`
                (`bot_id`, `id`, `type`, `title`, `username`, `all_members_are_administrators`, `created_at` ,`updated_at`, `old_id`)
                VALUES
                (' . self::$bot_id . ', :id, :type, :title, :username, :all_members_are_administrators, :created_at, :updated_at, :old_id)
                ON DUPLICATE KEY UPDATE
                    `type`                           = VALUES(`type`),
                    `title`                          = VALUES(`title`),
                    `username`                       = VALUES(`username`),
                    `all_members_are_administrators` = VALUES(`all_members_are_administrators`),
                    `updated_at`                     = VALUES(`updated_at`)
            ');

            $chat_id = $chat->getId();
            $chat_type = $chat->getType();

            if ($migrate_to_chat_id !== null) {
                $chat_type = 'supergroup';

                $sth->bindValue(':id', $migrate_to_chat_id);
                $sth->bindValue(':old_id', $chat_id);
            } else {
                $sth->bindValue(':id', $chat_id);
                $sth->bindValue(':old_id', $migrate_to_chat_id);
            }

            $sth->bindValue(':type', $chat_type);
            $sth->bindValue(':title', $chat->getTitle());
            $sth->bindValue(':username', $chat->getUsername());
            $sth->bindValue(':all_members_are_administrators', $chat->getAllMembersAreAdministrators(), PDO::PARAM_INT);
            $date = $date ?: self::getTimestamp();
            $sth->bindValue(':created_at', $date);
            $sth->bindValue(':updated_at', $date);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert request into database
     *
     * @todo self::$pdo->lastInsertId() - unsafe usage if expected previous insert fails?
     *
     * @param Update $update
     *
     * @return bool
     * @throws TelegramException
     */
    public static function insertRequest(Update $update)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $update_id = $update->getUpdateId();
        $update_type = $update->getUpdateType();

// @todo Make this simpler: if ($message = $update->getMessage()) ...
        if ($update_type === 'message') {
            $message = $update->getMessage();

            if (self::insertMessageRequest($message)) {
                $message_id = $message->getMessageId();
                $chat_id = $message->getChat()->getId();

                return self::insertTelegramUpdate(
                        $update_id, $chat_id, $message_id
                );
            }
        } elseif ($update_type === 'edited_message') {
            $edited_message = $update->getEditedMessage();

            if (self::insertEditedMessageRequest($edited_message)) {
                $edited_message_local_id = self::$pdo->lastInsertId();
                $chat_id = $edited_message->getChat()->getId();

                return self::insertTelegramUpdate(
                        $update_id, $chat_id, null, null, null, null, $edited_message_local_id
                );
            }
        } elseif ($update_type === 'channel_post') {
            $channel_post = $update->getChannelPost();

            if (self::insertMessageRequest($channel_post)) {
                $message_id = $channel_post->getMessageId();
                $chat_id = $channel_post->getChat()->getId();

                return self::insertTelegramUpdate(
                        $update_id, $chat_id, $message_id
                );
            }
        } elseif ($update_type === 'edited_channel_post') {
            $edited_channel_post = $update->getEditedChannelPost();

            if (self::insertEditedMessageRequest($edited_channel_post)) {
                $edited_channel_post_local_id = self::$pdo->lastInsertId();
                $chat_id = $edited_channel_post->getChat()->getId();

                return self::insertTelegramUpdate(
                        $update_id, $chat_id, null, null, null, null, $edited_channel_post_local_id
                );
            }
        } elseif ($update_type === 'inline_query') {
            $inline_query = $update->getInlineQuery();

            if (self::insertInlineQueryRequest($inline_query)) {
                $inline_query_id = $inline_query->getId();

                return self::insertTelegramUpdate(
                        $update_id, null, null, $inline_query_id
                );
            }
        } elseif ($update_type === 'chosen_inline_result') {
            $chosen_inline_result = $update->getChosenInlineResult();

            if (self::insertChosenInlineResultRequest($chosen_inline_result)) {
                $chosen_inline_result_local_id = self::$pdo->lastInsertId();

                return self::insertTelegramUpdate(
                        $update_id, null, null, null, $chosen_inline_result_local_id
                );
            }
        } elseif ($update_type === 'callback_query') {
            $callback_query = $update->getCallbackQuery();

            if (self::insertCallbackQueryRequest($callback_query)) {
                $callback_query_id = $callback_query->getId();

                return self::insertTelegramUpdate(
                        $update_id, null, null, null, null, $callback_query_id
                );
            }
        }

        return false;
    }

    /**
     * Insert inline query request into database
     *
     * @param InlineQuery $inline_query
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertInlineQueryRequest(InlineQuery $inline_query)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_INLINE_QUERY . '`
                (`bot_id`, `id`, `user_id`, `location`, `query`, `offset`, `created_at`)
                VALUES
                (' . self::$bot_id . ', :id, :user_id, :location, :query, :offset, :created_at)
            ');

            $date = self::getTimestamp();
            $user_id = null;

            $user = $inline_query->getFrom();
            if ($user instanceof User) {
                $user_id = $user->getId();
                self::insertUser($user, $date);
            }

            $sth->bindValue(':id', $inline_query->getId());
            $sth->bindValue(':user_id', $user_id);
            $sth->bindValue(':location', $inline_query->getLocation());
            $sth->bindValue(':query', $inline_query->getQuery());
            $sth->bindValue(':offset', $inline_query->getOffset());
            $sth->bindValue(':created_at', $date);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert chosen inline result request into database
     *
     * @param ChosenInlineResult $chosen_inline_result
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertChosenInlineResultRequest(ChosenInlineResult $chosen_inline_result)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT INTO `' . TB_CHOSEN_INLINE_RESULT . '`
                (`bot_id`, `result_id`, `user_id`, `location`, `inline_message_id`, `query`, `created_at`)
                VALUES
                (' . self::$bot_id . ', :result_id, :user_id, :location, :inline_message_id, :query, :created_at)
            ');

            $date = self::getTimestamp();
            $user_id = null;

            $user = $chosen_inline_result->getFrom();
            if ($user instanceof User) {
                $user_id = $user->getId();
                self::insertUser($user, $date);
            }

            $sth->bindValue(':result_id', $chosen_inline_result->getResultId());
            $sth->bindValue(':user_id', $user_id);
            $sth->bindValue(':location', $chosen_inline_result->getLocation());
            $sth->bindValue(':inline_message_id', $chosen_inline_result->getInlineMessageId());
            $sth->bindValue(':query', $chosen_inline_result->getQuery());
            $sth->bindValue(':created_at', $date);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert callback query request into database
     *
     * @param CallbackQuery $callback_query
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertCallbackQueryRequest(CallbackQuery $callback_query)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_CALLBACK_QUERY . '`
                (`bot_id`, `id`, `user_id`, `chat_id`, `message_id`, `inline_message_id`, `data`, `created_at`)
                VALUES
                (' . self::$bot_id . ', :id, :user_id, :chat_id, :message_id, :inline_message_id, :data, :created_at)
            ');

            $date = self::getTimestamp();
            $user_id = null;

            $user = $callback_query->getFrom();
            if ($user instanceof User) {
                $user_id = $user->getId();
                self::insertUser($user, $date);
            }

            $message = $callback_query->getMessage();
            $chat_id = null;
            $message_id = null;
            if ($message instanceof Message) {
                $chat_id = $message->getChat()->getId();
                $message_id = $message->getMessageId();

                $is_message = self::$pdo->query('
                    SELECT *
                    FROM `' . TB_MESSAGE . '`
                    WHERE `bot_id`= ' . self::$bot_id . ' AND `id` = ' . $message_id . '
                      AND `chat_id` = ' . $chat_id . '
                    LIMIT 1
                ')->rowCount();

                if ($is_message) {
                    self::insertEditedMessageRequest($message);
                } else {
                    self::insertMessageRequest($message);
                }
            }

            $sth->bindValue(':id', $callback_query->getId());
            $sth->bindValue(':user_id', $user_id);
            $sth->bindValue(':chat_id', $chat_id);
            $sth->bindValue(':message_id', $message_id);
            $sth->bindValue(':inline_message_id', $callback_query->getInlineMessageId());
            $sth->bindValue(':data', $callback_query->getData());
            $sth->bindValue(':created_at', $date);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert Message request in db
     *
     * @todo Complete with new fields: https://core.telegram.org/bots/api#message
     *
     * @param Message $message
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertMessageRequest(Message $message)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $date = self::getTimestamp($message->getDate());

// Insert chat, update chat id in case it migrated
        $chat = $message->getChat();
        self::insertChat($chat, $date, $message->getMigrateToChatId());

// Insert user and the relation with the chat
        $user = $message->getFrom();
        if ($user instanceof User) {
            self::insertUser($user, $date, $chat);
        }

// Insert the forwarded message user in users table
        $forward_date = null;
        $forward_from = $message->getForwardFrom();
        if ($forward_from instanceof User) {
            self::insertUser($forward_from, $forward_date);
            $forward_from = $forward_from->getId();
            $forward_date = self::getTimestamp($message->getForwardDate());
        }
        $forward_from_chat = $message->getForwardFromChat();
        if ($forward_from_chat instanceof Chat) {
            self::insertChat($forward_from_chat, $forward_date);
            $forward_from_chat = $forward_from_chat->getId();
            $forward_date = self::getTimestamp($message->getForwardDate());
        }

// New and left chat member
        $new_chat_members_ids = null;
        $left_chat_member_id = null;

        $new_chat_members = $message->getNewChatMembers();
        $left_chat_member = $message->getLeftChatMember();
        if (!empty($new_chat_members)) {
            foreach ($new_chat_members as $new_chat_member) {
                if ($new_chat_member instanceof User) {
// Insert the new chat user
                    self::insertUser($new_chat_member, $date, $chat);
                    $new_chat_members_ids[] = $new_chat_member->getId();
                }
            }
            $new_chat_members_ids = implode(',', $new_chat_members_ids);
        } elseif ($left_chat_member instanceof User) {
// Insert the left chat user
            self::insertUser($left_chat_member, $date, $chat);
            $left_chat_member_id = $left_chat_member->getId();
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_MESSAGE . '`
                (
                    `bot_id`, `id`, `user_id`, `chat_id`, `date`, `forward_from`, `forward_from_chat`, `forward_from_message_id`,
                    `forward_date`, `reply_to_chat`, `reply_to_message`, `media_group_id`, `text`, `entities`, `audio`, `document`,
                    `photo`, `sticker`, `video`, `voice`, `video_note`, `caption`, `contact`,
                    `location`, `venue`, `new_chat_members`, `left_chat_member`,
                    `new_chat_title`,`new_chat_photo`, `delete_chat_photo`, `group_chat_created`,
                    `supergroup_chat_created`, `channel_chat_created`,
                    `migrate_from_chat_id`, `migrate_to_chat_id`, `pinned_message`, `connected_website`
                ) VALUES (
                    ' . self::$bot_id . ', :message_id, :user_id, :chat_id, :date, :forward_from, :forward_from_chat, :forward_from_message_id,
                    :forward_date, :reply_to_chat, :reply_to_message, :media_group_id, :text, :entities, :audio, :document,
                    :photo, :sticker, :video, :voice, :video_note, :caption, :contact,
                    :location, :venue, :new_chat_members, :left_chat_member,
                    :new_chat_title, :new_chat_photo, :delete_chat_photo, :group_chat_created,
                    :supergroup_chat_created, :channel_chat_created,
                    :migrate_from_chat_id, :migrate_to_chat_id, :pinned_message, :connected_website
                )
            ');

            $user_id = null;
            if ($user instanceof User) {
                $user_id = $user->getId();
            }
            $chat_id = $chat->getId();

            $reply_to_message = $message->getReplyToMessage();
            $reply_to_message_id = null;
            if ($reply_to_message instanceof ReplyToMessage) {
                $reply_to_message_id = $reply_to_message->getMessageId();
// please notice that, as explained in the documentation, reply_to_message don't contain other
// reply_to_message field so recursion deep is 1
                self::insertMessageRequest($reply_to_message);
            }

            $sth->bindValue(':message_id', $message->getMessageId());
            $sth->bindValue(':chat_id', $chat_id);
            $sth->bindValue(':user_id', $user_id);
            $sth->bindValue(':date', $date);
            $sth->bindValue(':forward_from', $forward_from);
            $sth->bindValue(':forward_from_chat', $forward_from_chat);
            $sth->bindValue(':forward_from_message_id', $message->getForwardFromMessageId());
            $sth->bindValue(':forward_date', $forward_date);

            $reply_to_chat_id = null;
            if ($reply_to_message_id !== null) {
                $reply_to_chat_id = $chat_id;
            }
            $sth->bindValue(':reply_to_chat', $reply_to_chat_id);
            $sth->bindValue(':reply_to_message', $reply_to_message_id);

            $sth->bindValue(':media_group_id', $message->getMediaGroupId());
            $sth->bindValue(':text', $message->getText());
            $sth->bindValue(':entities', $t = self::entitiesArrayToJson($message->getEntities(), null));
            $sth->bindValue(':audio', $message->getAudio());
            $sth->bindValue(':document', $message->getDocument());
            $sth->bindValue(':photo', $t = self::entitiesArrayToJson($message->getPhoto(), null));
            $sth->bindValue(':sticker', $message->getSticker());
            $sth->bindValue(':video', $message->getVideo());
            $sth->bindValue(':voice', $message->getVoice());
            $sth->bindValue(':video_note', $message->getVideoNote());
            $sth->bindValue(':caption', $message->getCaption());
            $sth->bindValue(':contact', $message->getContact());
            $sth->bindValue(':location', $message->getLocation());
            $sth->bindValue(':venue', $message->getVenue());
            $sth->bindValue(':new_chat_members', $new_chat_members_ids);
            $sth->bindValue(':left_chat_member', $left_chat_member_id);
            $sth->bindValue(':new_chat_title', $message->getNewChatTitle());
            $sth->bindValue(':new_chat_photo', $t = self::entitiesArrayToJson($message->getNewChatPhoto(), null));
            $sth->bindValue(':delete_chat_photo', $message->getDeleteChatPhoto());
            $sth->bindValue(':group_chat_created', $message->getGroupChatCreated());
            $sth->bindValue(':supergroup_chat_created', $message->getSupergroupChatCreated());
            $sth->bindValue(':channel_chat_created', $message->getChannelChatCreated());
            $sth->bindValue(':migrate_from_chat_id', $message->getMigrateFromChatId());
            $sth->bindValue(':migrate_to_chat_id', $message->getMigrateToChatId());
            $sth->bindValue(':pinned_message', $message->getPinnedMessage());
            $sth->bindValue(':connected_website', $message->getConnectedWebsite());

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert Edited Message request in db
     *
     * @param Message $edited_message
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertEditedMessageRequest(Message $edited_message)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $edit_date = self::getTimestamp($edited_message->getEditDate());

// Insert chat
            $chat = $edited_message->getChat();
            self::insertChat($chat, $edit_date);

// Insert user and the relation with the chat
            $user = $edited_message->getFrom();
            if ($user instanceof User) {
                self::insertUser($user, $edit_date, $chat);
            }

            $sth = self::$pdo->prepare('
                INSERT IGNORE INTO `' . TB_EDITED_MESSAGE . '`
                (`bot_id`, `chat_id`, `message_id`, `user_id`, `edit_date`, `text`, `entities`, `caption`)
                VALUES
                (' . self::$bot_id . ', :chat_id, :message_id, :user_id, :edit_date, :text, :entities, :caption)
            ');

            $user_id = null;
            if ($user instanceof User) {
                $user_id = $user->getId();
            }

            $sth->bindValue(':chat_id', $chat->getId());
            $sth->bindValue(':message_id', $edited_message->getMessageId());
            $sth->bindValue(':user_id', $user_id);
            $sth->bindValue(':edit_date', $edit_date);
            $sth->bindValue(':text', $edited_message->getText());
            $sth->bindValue(':entities', self::entitiesArrayToJson($edited_message->getEntities(), null));
            $sth->bindValue(':caption', $edited_message->getCaption());

            return $sth->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Select Groups, Supergroups, Channels and/or single user Chats (also by ID or text)
     *
     * @param $select_chats_params
     *
     * @return array|bool
     * @throws TelegramException
     */
    public static function selectChats($select_chats_params)
    {
        if (!self::isDbConnected()) {
            return false;
        }

// Set defaults for omitted values.
        $select = array_merge([
            'groups' => true,
            'supergroups' => true,
            'channels' => true,
            'users' => true,
            'date_from' => null,
            'date_to' => null,
            'chat_id' => null,
            'text' => null,
            ], $select_chats_params);

        if (!$select['groups'] && !$select['users'] && !$select['supergroups'] && !$select['channels']) {
            return false;
        }

        try {
            $query = '
                SELECT * ,
                ' . TB_CHAT . '.`id` AS `chat_id`,
                ' . TB_CHAT . '.`username` AS `chat_username`,
                ' . TB_CHAT . '.`created_at` AS `chat_created_at`,
                ' . TB_CHAT . '.`updated_at` AS `chat_updated_at`
            ';
            if ($select['users']) {
                $query .= '
                    , ' . TB_USER . '.`id` AS `user_id`
                    FROM `' . TB_CHAT . '`
                    LEFT JOIN `' . TB_USER . '`
                    ON ' . TB_CHAT . '.`id`=' . TB_USER . '.`id`
                ';
            } else {
                $query .= 'FROM `' . TB_CHAT . '`';
            }

// Building parts of query
            $where = [];
            $tokens = [];

            if (!$select['groups'] || !$select['users'] || !$select['supergroups'] || !$select['channels']) {
                $chat_or_user = [];

                $select['groups'] && $chat_or_user[] = TB_CHAT . '.`type` = "group"';
                $select['supergroups'] && $chat_or_user[] = TB_CHAT . '.`type` = "supergroup"';
                $select['channels'] && $chat_or_user[] = TB_CHAT . '.`type` = "channel"';
                $select['users'] && $chat_or_user[] = TB_CHAT . '.`type` = "private"';

                $where[] = '(' . implode(' OR ', $chat_or_user) . ')';
            }

            if (null !== $select['date_from']) {
                $where[] = TB_CHAT . '.`updated_at` >= :date_from';
                $tokens[':date_from'] = $select['date_from'];
            }

            if (null !== $select['date_to']) {
                $where[] = TB_CHAT . '.`updated_at` <= :date_to';
                $tokens[':date_to'] = $select['date_to'];
            }

            if (null !== $select['chat_id']) {
                $where[] = TB_CHAT . '.`id` = :chat_id';
                $tokens[':chat_id'] = $select['chat_id'];
            }

            if (null !== $select['text']) {
                $text_like = '%' . strtolower($select['text']) . '%';
                if ($select['users']) {
                    $where[] = '(
                        LOWER(' . TB_CHAT . '.`title`) LIKE :text1
                        OR LOWER(' . TB_USER . '.`first_name`) LIKE :text2
                        OR LOWER(' . TB_USER . '.`last_name`) LIKE :text3
                        OR LOWER(' . TB_USER . '.`username`) LIKE :text4
                    )';
                    $tokens[':text1'] = $text_like;
                    $tokens[':text2'] = $text_like;
                    $tokens[':text3'] = $text_like;
                    $tokens[':text4'] = $text_like;
                } else {
                    $where[] = 'LOWER(' . TB_CHAT . '.`title`) LIKE :text';
                    $tokens[':text'] = $text_like;
                }
            }

            if (!empty($where)) {
                $query .= ' WHERE ' . TB_CHAT . '.bot_id`= ' . self::$bot_id . ' AND ' . implode(' AND ', $where);
            } else {
                $query .= ' WHERE ' . TB_CHAT . '.`bot_id`= ' . self::$bot_id;
            }

            $query .= ' ORDER BY ' . TB_CHAT . '.`updated_at` ASC';

            $sth = self::$pdo->prepare($query);
            $sth->execute($tokens);

            return $sth->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Get Telegram API request count for current chat / message
     *
     * @param integer $chat_id
     * @param string  $inline_message_id
     *
     * @return array|bool Array containing TOTAL and CURRENT fields or false on invalid arguments
     * @throws TelegramException
     */
    public static function getTelegramRequestCount($chat_id = null, $inline_message_id = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('SELECT
                (SELECT COUNT(DISTINCT `chat_id`) FROM `' . TB_REQUEST_LIMITER . '` WHERE `bot_id`= ' . self::$bot_id . ' AND `created_at` >= :created_at_1) AS LIMIT_PER_SEC_ALL,
                (SELECT COUNT(*) FROM `' . TB_REQUEST_LIMITER . '` WHERE `bot_id`= ' . self::$bot_id . ' AND `created_at` >= :created_at_2 AND ((`chat_id` = :chat_id_1 AND `inline_message_id` IS NULL) OR (`inline_message_id` = :inline_message_id AND `chat_id` IS NULL))) AS LIMIT_PER_SEC,
                (SELECT COUNT(*) FROM `' . TB_REQUEST_LIMITER . '` WHERE `bot_id`= ' . self::$bot_id . ' AND `created_at` >= :created_at_minute AND `chat_id` = :chat_id_2) AS LIMIT_PER_MINUTE
            ');

            $date = self::getTimestamp();
            $date_minute = self::getTimestamp(strtotime('-1 minute'));

            $sth->bindValue(':chat_id_1', $chat_id);
            $sth->bindValue(':chat_id_2', $chat_id);
            $sth->bindValue(':inline_message_id', $inline_message_id);
            $sth->bindValue(':created_at_1', $date);
            $sth->bindValue(':created_at_2', $date);
            $sth->bindValue(':created_at_minute', $date_minute);

            $sth->execute();

            return $sth->fetch();
        } catch (Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Insert Telegram API request in db
     *
     * @param string $method
     * @param array  $data
     *
     * @return bool If the insert was successful
     * @throws TelegramException
     */
    public static function insertTelegramRequest($method, $data)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('INSERT INTO `' . TB_REQUEST_LIMITER . '`
                (`bot_id`, `method`, `chat_id`, `inline_message_id`, `created_at`)
                VALUES
                (' . self::$bot_id . ', :method, :chat_id, :inline_message_id, :created_at);
            ');

            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : null;
            $inline_message_id = isset($data['inline_message_id']) ? $data['inline_message_id'] : null;

            $sth->bindValue(':chat_id', $chat_id);
            $sth->bindValue(':inline_message_id', $inline_message_id);
            $sth->bindValue(':method', $method);
            $sth->bindValue(':created_at', self::getTimestamp());

            return $sth->execute();
        } catch (Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Bulk update the entries of any table
     *
     * @param string $table
     * @param array  $fields_values
     * @param array  $where_fields_values
     *
     * @return bool
     * @throws TelegramException
     */
    public static function update($table, array $fields_values, array $where_fields_values)
    {
        if (empty($fields_values) || !self::isDbConnected()) {
            return false;
        }

        try {
// Building parts of query
            $tokens = $fields = $where = [];

// Fields with values to update
            foreach ($fields_values as $field => $value) {
                $token = ':' . count($tokens);
                $fields[] = "`{$field}` = {$token}";
                $tokens[$token] = $value;
            }

// Where conditions
            foreach ($where_fields_values as $field => $value) {
                $token = ':' . count($tokens);
                $where[] = "`{$field}` = {$token}";
                $tokens[$token] = $value;
            }

            $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $fields);
            $sql .= count($where) > 0 ? ' WHERE `bot_id`= ' . self::$bot_id . ' AND ' . implode(' AND ', $where) : '';

            return self::$pdo->prepare($sql)->execute($tokens);
        } catch (Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * Create tables with prefix if not exist
     *
     * @return bool
     * @throws TelegramException
     */
    public static function createTables()
    {
        if (!self::isDbConnected()) {
            return false;
        }
        $tables = [];
        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "user` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint COMMENT 'Unique user identifier',
  `is_bot` tinyint(1) DEFAULT 0 COMMENT 'True if this user is a bot',
  `first_name` CHAR(255) NOT NULL DEFAULT '' COMMENT 'User''s first name',
  `last_name` CHAR(255) DEFAULT NULL COMMENT 'User''s last name',
  `username` CHAR(191) DEFAULT NULL COMMENT 'User''s username',
  `language_code` CHAR(10) DEFAULT NULL COMMENT 'User''s system language',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "chat` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint COMMENT 'Unique user or chat identifier',
  `type` ENUM('private', 'group', 'supergroup', 'channel') NOT NULL COMMENT 'Chat type, either private, group, supergroup or channel',
  `title` CHAR(255) DEFAULT '' COMMENT 'Chat (group) title, is null if chat type is private',
  `username` CHAR(255) DEFAULT NULL COMMENT 'Username, for private chats, supergroups and channels if available',
  `all_members_are_administrators` tinyint(1) DEFAULT 0 COMMENT 'True if a all members of this group are admins',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',
  `old_id` bigint DEFAULT NULL COMMENT 'Unique chat identifier, this is filled when a group is converted to a supergroup',

  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),
  KEY `old_id` (`old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "user_chat` (
  `user_id` bigint COMMENT 'Unique user identifier',
  `chat_id` bigint COMMENT 'Unique user or chat identifier',

  PRIMARY KEY (`user_id`, `chat_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`chat_id`) REFERENCES `" . self::table_prefix . "chat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "inline_query` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED COMMENT 'Unique identifier for this query',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `location` CHAR(255) NULL DEFAULT NULL COMMENT 'Location of the user',
  `query` TEXT NOT NULL COMMENT 'Text of the query',
  `offset` CHAR(255) NULL DEFAULT NULL COMMENT 'Offset of the result',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "chosen_inline_result` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `result_id` CHAR(255) NOT NULL DEFAULT '' COMMENT 'Identifier for this result',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `location` CHAR(255) NULL DEFAULT NULL COMMENT 'Location object, user''s location',
  `inline_message_id` CHAR(255) NULL DEFAULT NULL COMMENT 'Identifier of the sent inline message',
  `query` TEXT NOT NULL COMMENT 'The query that was used to obtain the result',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "message` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `chat_id` bigint COMMENT 'Unique chat identifier',
  `id` bigint UNSIGNED COMMENT 'Unique message identifier',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `date` timestamp NULL DEFAULT NULL COMMENT 'Date the message was sent in timestamp format',
  `forward_from` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier, sender of the original message',
  `forward_from_chat` bigint NULL DEFAULT NULL COMMENT 'Unique chat identifier, chat the original message belongs to',
  `forward_from_message_id` bigint NULL DEFAULT NULL COMMENT 'Unique chat identifier of the original message in the channel',
  `forward_date` timestamp NULL DEFAULT NULL COMMENT 'date the original message was sent in timestamp format',
  `reply_to_chat` bigint NULL DEFAULT NULL COMMENT 'Unique chat identifier',
  `reply_to_message` bigint UNSIGNED DEFAULT NULL COMMENT 'Message that this message is reply to',
  `media_group_id` TEXT COMMENT 'The unique identifier of a media message group this message belongs to',
  `text` TEXT COMMENT 'For text messages, the actual UTF-8 text of the message max message length 4096 char utf8mb4',
  `entities` TEXT COMMENT 'For text messages, special entities like usernames, URLs, bot commands, etc. that appear in the text',
  `audio` TEXT COMMENT 'Audio object. Message is an audio file, information about the file',
  `document` TEXT COMMENT 'Document object. Message is a general file, information about the file',
  `photo` TEXT COMMENT 'Array of PhotoSize objects. Message is a photo, available sizes of the photo',
  `sticker` TEXT COMMENT 'Sticker object. Message is a sticker, information about the sticker',
  `video` TEXT COMMENT 'Video object. Message is a video, information about the video',
  `voice` TEXT COMMENT 'Voice Object. Message is a Voice, information about the Voice',
  `video_note` TEXT COMMENT 'VoiceNote Object. Message is a Video Note, information about the Video Note',
  `contact` TEXT COMMENT 'Contact object. Message is a shared contact, information about the contact',
  `location` TEXT COMMENT 'Location object. Message is a shared location, information about the location',
  `venue` TEXT COMMENT 'Venue object. Message is a Venue, information about the Venue',
  `caption` TEXT COMMENT  'For message with caption, the actual UTF-8 text of the caption',
  `new_chat_members` TEXT COMMENT 'List of unique user identifiers, new member(s) were added to the group, information about them (one of these members may be the bot itself)',
  `left_chat_member` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier, a member was removed from the group, information about them (this member may be the bot itself)',
  `new_chat_title` CHAR(255) DEFAULT NULL COMMENT 'A chat title was changed to this value',
  `new_chat_photo` TEXT COMMENT 'Array of PhotoSize objects. A chat photo was change to this value',
  `delete_chat_photo` tinyint(1) DEFAULT 0 COMMENT 'Informs that the chat photo was deleted',
  `group_chat_created` tinyint(1) DEFAULT 0 COMMENT 'Informs that the group has been created',
  `supergroup_chat_created` tinyint(1) DEFAULT 0 COMMENT 'Informs that the supergroup has been created',
  `channel_chat_created` tinyint(1) DEFAULT 0 COMMENT 'Informs that the channel chat has been created',
  `migrate_to_chat_id` bigint NULL DEFAULT NULL COMMENT 'Migrate to chat identifier. The group has been migrated to a supergroup with the specified identifier',
  `migrate_from_chat_id` bigint NULL DEFAULT NULL COMMENT 'Migrate from chat identifier. The supergroup has been migrated from a group with the specified identifier',
  `pinned_message` TEXT NULL COMMENT 'Message object. Specified message was pinned',
  `connected_website` TEXT NULL COMMENT 'The domain name of the website on which the user has logged in.',

  PRIMARY KEY (`chat_id`, `id`),
  KEY `user_id` (`user_id`),
  KEY `forward_from` (`forward_from`),
  KEY `forward_from_chat` (`forward_from_chat`),
  KEY `reply_to_chat` (`reply_to_chat`),
  KEY `reply_to_message` (`reply_to_message`),
  KEY `left_chat_member` (`left_chat_member`),
  KEY `migrate_from_chat_id` (`migrate_from_chat_id`),
  KEY `migrate_to_chat_id` (`migrate_to_chat_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`),
  FOREIGN KEY (`chat_id`) REFERENCES `" . self::table_prefix . "chat` (`id`),
  FOREIGN KEY (`forward_from`) REFERENCES `" . self::table_prefix . "user` (`id`),
  FOREIGN KEY (`forward_from_chat`) REFERENCES `" . self::table_prefix . "chat` (`id`),
  FOREIGN KEY (`reply_to_chat`, `reply_to_message`) REFERENCES `" . self::table_prefix . "message` (`chat_id`, `id`),
  FOREIGN KEY (`forward_from`) REFERENCES `" . self::table_prefix . "user` (`id`),
  FOREIGN KEY (`left_chat_member`) REFERENCES `" . self::table_prefix . "user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "callback_query` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED COMMENT 'Unique identifier for this query',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `chat_id` bigint NULL COMMENT 'Unique chat identifier',
  `message_id` bigint UNSIGNED COMMENT 'Unique message identifier',
  `inline_message_id` CHAR(255) NULL DEFAULT NULL COMMENT 'Identifier of the message sent via the bot in inline mode, that originated the query',
  `data` CHAR(255) NOT NULL DEFAULT '' COMMENT 'Data associated with the callback button',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `chat_id` (`chat_id`),
  KEY `message_id` (`message_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`),
  FOREIGN KEY (`chat_id`, `message_id`) REFERENCES `" . self::table_prefix . "message` (`chat_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "edited_message` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `chat_id` bigint COMMENT 'Unique chat identifier',
  `message_id` bigint UNSIGNED COMMENT 'Unique message identifier',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `edit_date` timestamp NULL DEFAULT NULL COMMENT 'Date the message was edited in timestamp format',
  `text` TEXT COMMENT 'For text messages, the actual UTF-8 text of the message max message length 4096 char utf8',
  `entities` TEXT COMMENT 'For text messages, special entities like usernames, URLs, bot commands, etc. that appear in the text',
  `caption` TEXT COMMENT  'For message with caption, the actual UTF-8 text of the caption',

  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  KEY `message_id` (`message_id`),
  KEY `user_id` (`user_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`chat_id`) REFERENCES `" . self::table_prefix . "chat` (`id`),
  FOREIGN KEY (`chat_id`, `message_id`) REFERENCES `" . self::table_prefix . "message` (`chat_id`, `id`),
  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "telegram_update` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED COMMENT 'Update''s unique identifier',
  `chat_id` bigint NULL DEFAULT NULL COMMENT 'Unique chat identifier',
  `message_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Unique message identifier',
  `inline_query_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Unique inline query identifier',
  `chosen_inline_result_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Local chosen inline result identifier',
  `callback_query_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Unique callback query identifier',
  `edited_message_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Local edited message identifier',

  PRIMARY KEY (`id`),
  KEY `message_id` (`chat_id`, `message_id`),
  KEY `inline_query_id` (`inline_query_id`),
  KEY `chosen_inline_result_id` (`chosen_inline_result_id`),
  KEY `callback_query_id` (`callback_query_id`),
  KEY `edited_message_id` (`edited_message_id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`chat_id`, `message_id`) REFERENCES `" . self::table_prefix . "message` (`chat_id`, `id`),
  FOREIGN KEY (`inline_query_id`) REFERENCES `" . self::table_prefix . "inline_query` (`id`),
  FOREIGN KEY (`chosen_inline_result_id`) REFERENCES `" . self::table_prefix . "chosen_inline_result` (`id`),
  FOREIGN KEY (`callback_query_id`) REFERENCES `" . self::table_prefix . "callback_query` (`id`),
  FOREIGN KEY (`edited_message_id`) REFERENCES `" . self::table_prefix . "edited_message` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "conversation` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint(20) unsigned AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `user_id` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier',
  `chat_id` bigint NULL DEFAULT NULL COMMENT 'Unique user or chat identifier',
  `status` ENUM('active', 'cancelled', 'stopped') NOT NULL DEFAULT 'active' COMMENT 'Conversation state',
  `command` varchar(160) DEFAULT '' COMMENT 'Default command to execute',
  `notes` text DEFAULT NULL COMMENT 'Data stored from command',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `chat_id` (`chat_id`),
  KEY `status` (`status`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`),
  FOREIGN KEY (`chat_id`) REFERENCES `" . self::table_prefix . "chat` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "botan_shortener` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `user_id` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier',
  `url` text NOT NULL COMMENT 'Original URL',
  `short_url` CHAR(255) NOT NULL DEFAULT '' COMMENT 'Shortened URL',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),

  FOREIGN KEY (`user_id`) REFERENCES `" . self::table_prefix . "user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        $tables[] = "CREATE TABLE IF NOT EXISTS `" . self::table_prefix . "" . self::table_prefix . "request_limiter` (
  `bot_id` bigint(20) unsigned NOT NULL COMMENT 'Bot identifier',
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `chat_id` char(255) NULL DEFAULT NULL COMMENT 'Unique chat identifier',
  `inline_message_id` char(255) NULL DEFAULT NULL COMMENT 'Identifier of the sent inline message',
  `method` char(255) DEFAULT NULL COMMENT 'Request method',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";

        try {
            foreach ($tables as $table) {
                self::$pdo->prepare($table)->execute();
            }
        } catch (Exception $e) {
            throw new TelegramException($e->getMessage());
        }
        return true;
    }
}
