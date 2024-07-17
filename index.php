<?php
header('Content-Type: application/json');

// Path to the directory where conversation contexts will be stored
define('CONTEXT_DIR', __DIR__ . '/contexts');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Get the OpenAI API key from headers
    $headers = getallheaders();
    $api_key = isset($headers['OpenAI-API-Key']) ? $headers['OpenAI-API-Key'] : null;

    if (!$api_key) {
        echo json_encode(['error' => 'OpenAI API key not provided']);
        exit;
    }

    // Check if the required fields are set in the received JSON
    if (isset($input['ai_prompt']) && isset($input['user_message']) && isset($input['thread_id'])) {
        $ai_prompt = $input['ai_prompt'];
        $user_message = $input['user_message'];
        $thread_id = $input['thread_id'];
        
        // Retrieve previous conversation context if thread_id is provided
        $context = get_conversation_context($thread_id);

        // Combine ai_prompt, previous context, and user_message to form the complete prompt for OpenAI
        $prompt = $ai_prompt . "\n" . $context . "\nUser: " . $user_message . "\nAI:";

        // Optional parameters with default values
        $max_tokens = 100;
        $temperature = 0.7;
        $top_p = 1.0;
        $n = 1;
        $stop = null;

        // Call the OpenAI API with the combined prompt
        $openai_response = call_openai_api($api_key, 'text-davinci-003', $prompt, $max_tokens, $temperature, $top_p, $n, $stop);

        if ($openai_response) {
            // Update the conversation context with the new user_message and OpenAI response
            update_conversation_context($thread_id, $user_message, $openai_response);

            // Send the OpenAI response back to the user
            echo json_encode(['response' => $openai_response]);
        } else {
            echo json_encode(['error' => 'Failed to get response from OpenAI']);
        }
    } else {
        echo json_encode(['error' => 'Invalid input data']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

// Function to call the OpenAI API
function call_openai_api($api_key, $model, $prompt, $max_tokens, $temperature, $top_p, $n, $stop) {
    $api_url = 'https://api.openai.com/v1/engines/' . $model . '/completions';

    $data = [
        'prompt' => $prompt,
        'max_tokens' => $max_tokens,
        'temperature' => $temperature,
        'top_p' => $top_p,
        'n' => $n,
        'stop' => $stop
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['text'])) {
        return $result['choices'][0]['text'];
    } else {
        return false;
    }
}

// Function to retrieve the conversation context
function get_conversation_context($thread_id) {
    $context_file = CONTEXT_DIR . '/' . $thread_id . '.txt';
    if (file_exists($context_file)) {
        return file_get_contents($context_file);
    }
    return '';
}

// Function to update the conversation context
function update_conversation_context($thread_id, $user_message, $ai_response) {
    $context_file = CONTEXT_DIR . '/' . $thread_id . '.txt';
    $new_context = "User: " . $user_message . "\nAI: " . $ai_response . "\n";
    file_put_contents($context_file, $new_context, FILE_APPEND);
}
?>
