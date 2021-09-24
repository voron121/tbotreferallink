<?php

namespace Unitix;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;

require_once __DIR__ . "/../vendor/autoload.php";

try {
    $telegram = new TelegramTools(TELEGRAM_BOT_TOKEN);
    $response = $telegram->getCallBackData();
    if (empty($response) || empty($response["message"]["text"])) {
        Throw new \Exception("В ответе от бота нет нужных данных: " . json_encode($response));
    }
    $message = $response["message"]["text"];
    // Если в сообщении нет refid - остановим скрипт
    if (!preg_match("/^(\/start)/", $message)) {
        mail(ADMIN_EMAIL, "Unitix telegram bot: info", $message);
        exit;
    }

    $referalId = preg_replace("(\/start\s)", "" , $message);
    $userName = $response["message"]["from"]["first_name"] . " " .$response["message"]["from"]["last_name"];
    $userLogin = "@" . $response["message"]["from"]["username"];
    /*
     * Получить все логины в телеграм пользователя из отчета и проверять наличие. Это поможет избежать дублей
     */
    $client = new Google_Client();
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    $client->setAuthConfig(GOOGLE_API_TOKEN);
    $client->setAccessType('offline');
    $service = new Google_Service_Sheets($client);

    // Проверим существует ли логин нового клиента в списке приведенных клиентов по реф.ссылке
    $response = $service->spreadsheets_values->get(REPORT_SPREADSHEET_ID, "D2:D9999999");
    $telegramClientsLogins = $response->getValues();
    // TODO: не оптимально. Скорость обработки данных в цикле желает лучшего
    for ($i = 0; $i <= count($telegramClientsLogins); $i++) {
        if (in_array($userLogin, $telegramClientsLogins[$i])) {
            mail(ADMIN_EMAIL, "Unitix telegram bot: info", $userLogin . " уже присутствует в таблице");
            exit;
        }
    }
    unset($telegramClientsLogins);

    // Запишем данные в таблицу
    $values = [[date("Y-m-d H:i:s"), $referalId, $userName, $userLogin]];
    $body    = new Google_Service_Sheets_ValueRange( [ 'values' => $values ] );
    $options = ['valueInputOption' => 'RAW'];
    $service->spreadsheets_values->append( REPORT_SPREADSHEET_ID, 'A2', $body, $options );
    exit;
} catch(\Throwable $e) {
    mail(ADMIN_EMAIL, "Unitix telegram bot: error", $e->getMessage());
}
?>