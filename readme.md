# The Quest of the Forgotten Realm

Welcome to **The Quest of the Forgotten Realm**, an interactive text-based adventure game that runs in your command-line interface (CLI). This game leverages OpenAI's ChatGPT API to provide a dynamic and immersive storytelling experience.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage](#usage)
- [Gameplay Instructions](#gameplay-instructions)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)
- [Customization](#customization)
- [License](#license)

---

## Prerequisites

Before you begin, ensure you have met the following requirements:

- **PHP 7.4 or higher** is installed on your system.
  - Check your PHP version by running `php -v` in your command line.
  - If PHP is not installed, download it from the [official website](https://www.php.net/downloads.php) or use a package manager.
- An **OpenAI API key**.
  - Sign up at [OpenAI's website](https://platform.openai.com/signup/) if you don't have an account.
  - Generate an API key from your [API keys page](https://platform.openai.com/account/api-keys).

---

## Installation

1. **Clone the Repository**

   Clone the GitHub repository to your local machine using the following command:

   ```bash
   git clone https://github.com/yourusername/your-repo-name.git
   ```

   Replace `yourusername/your-repo-name` with the actual GitHub username and repository name.

2. **Navigate to the Project Directory**

   ```bash
   cd your-repo-name
   ```

---

## Usage

### Running the Game

1. **Start the Game**

   Run the `game.php` script using PHP:

   ```bash
   php game.php
   ```

2. **Enter Your OpenAI API Key**

   - **First-Time Setup:**

     The script will prompt you to enter your OpenAI API key:

     ```
     Please enter your OpenAI API key:
     ```

     - Paste your API key and press **Enter**.
     - The API key will be saved securely in a hidden file for future use.

   - **Subsequent Runs:**

     - The script will use the saved API key automatically.

### Gameplay Instructions

- **Game Start**

  After entering your API key, the game will display a welcome message:

  ```
  Welcome to 'The Quest of the Forgotten Realm'!
  (Type 'exit' or 'quit' to end the game at any time.)
  ```

- **Receiving Narration**

  The game will provide vivid descriptions of the environment and scenarios.

- **Taking Actions**

  When prompted with `Your action:`, type in what you want your character to do and press **Enter**.

  - **Examples:**
    - `explore the forest`
    - `take the ancient book`
    - `talk to the mysterious stranger`
    - `cast a fire spell`

- **Exiting the Game**

  Type `exit` or `quit` at any prompt to end the game.

---

## Security Considerations

- **API Key Storage**

  - Your API key is stored in a hidden file named `.openai_api_key` in the project directory.
  - The file permissions are set to `0600`, making it readable and writable only by your user account.

- **Protecting Your API Key**

  - **Do Not Share:** Never share your API key or commit it to any public repository.
  - **Revoking Access:** If you believe your API key has been compromised, revoke it immediately from your OpenAI account settings.

- **File Permissions**

  - Ensure that the project directory is secure and not accessible by unauthorized users.
  - Verify file permissions if you're running the script on a shared system.

---

## Troubleshooting

### Common Issues and Solutions

1. **PHP Not Recognized**

   - **Error Message:** `'php' is not recognized as an internal or external command...`
   - **Solution:** Ensure PHP is installed and added to your system's PATH variable.

2. **Invalid API Key**

   - **Error Message:** `API Error Message: Incorrect API key provided`
   - **Solution:** Double-check your API key for typos and ensure it's valid.

3. **API Connection Errors**

   - **Error Message:** `Request Error: Could not resolve host: api.openai.com`
   - **Solution:** Check your internet connection and DNS settings.

4. **SSL Certificate Issues**

   - **Error Message:** `SSL certificate problem: unable to get local issuer certificate`
   - **Solution:** Update your system's CA certificates or disable SSL verification (not recommended).

     ```php
     // Add this line before executing the API request
     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     ```

     **Warning:** Disabling SSL verification can expose you to security risks.

5. **Permission Denied**

   - **Error Message:** `Permission denied` when accessing `.openai_api_key`
   - **Solution:** Ensure you have read/write permissions for the project directory and files.

---

## Customization

- **Changing the Game Theme**

  - Open `game.php` in a text editor.
  - Locate the `$system_prompt` variable.
  - Modify the prompt to change the game's setting or rules.

    ```php
    $system_prompt = "You are an interactive sci-fi adventure game set in space...";
    ```

- **Adjusting API Parameters**

  - Modify parameters like `max_tokens` and `temperature` in the `$data` array to change response length and creativity.

    ```php
    'max_tokens' => 250,
    'temperature' => 0.9,
    ```

- **Using a Different Model**

  - If you have access to GPT-4, you can change the model:

    ```php
    'model' => 'gpt-4o-mini',
    ```

---

## License

This project is licensed under the [MIT License](LICENSE). You are free to use, modify, and distribute this software as per the license terms.

---

## Credits

This game was created by [me@spencerthayer.com](me@spencerthayer.com). 

I also participated, [nsbucky](kenrick@thebusypixel.com).