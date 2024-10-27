# The Quest of the Forgotten Realm

Welcome to **The Quest of the Forgotten Realm**, an interactive text-based adventure game created during **Cascadia PHP 2024**. This project leverages OpenAI's ChatGPT and DALL·E 2 APIs to deliver a dynamic and immersive storytelling experience directly in your command-line interface (CLI). A heartfelt thank you to the organizers and participants of Cascadia PHP 2024 for inspiring and supporting this project!

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage](#usage)
  - [Starting the Game](#starting-the-game)
  - [Command-Line Options](#command-line-options)
- [Gameplay Instructions](#gameplay-instructions)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)
- [Customization](#customization)
- [License](#license)
- [Credits](#credits)

---

## Prerequisites

Before you begin, ensure you have met the following requirements:

- **PHP 7.4 or Higher** is installed on your system.
  - Check your PHP version by running:
    ```bash
    php -v
    ```
  - If PHP is not installed, download it from the [official website](https://www.php.net/downloads.php) or use a package manager.

- An **OpenAI API Key**.
  - Sign up at [OpenAI's website](https://platform.openai.com/signup/) if you don't have an account.
  - Generate an API key from your [API keys page](https://platform.openai.com/account/api-keys).

- **Git** installed (optional, for cloning the repository).
  - Check if Git is installed by running:
    ```bash
    git --version
    ```
  - If not installed, download it from the [official website](https://git-scm.com/downloads) or use a package manager.

---

## Installation

1. **Clone the Repository**

   Clone the GitHub repository to your local machine using the following command:

   ```bash
   git clone https://github.com/yourusername/quest-of-the-forgotten-realm.git
   ```

   Replace `yourusername/quest-of-the-forgotten-realm` with the actual GitHub username and repository name.

2. **Navigate to the Project Directory**

   ```bash
   cd quest-of-the-forgotten-realm
   ```

3. **Ensure Dependencies Are Met**

   - The project relies on PHP's `curl` and `gd` extensions.
   - Verify they are installed by running:
     ```bash
     php -m | grep -E 'curl|gd'
     ```
   - If not installed, refer to your operating system's package manager or PHP installation guide to add these extensions.

4. **Create Necessary Directories**

   The game will automatically create an `images` directory for temporary image storage. Ensure the PHP process has permissions to create directories in the project path.

---

## Usage

### Starting the Game

Run the `game.php` script using PHP:

```bash
php game.php
```

**First-Time Setup:**

- Upon the first run, you'll be prompted to enter your OpenAI API key:

  ```
  Please enter your OpenAI API key:
  ```

  - Paste your API key and press **Enter**.
  - The API key will be saved securely in a hidden file named `.openai_api_key` with permissions set to `0600` (readable and writable only by you).

**Subsequent Runs:**

- The script will automatically use the saved API key without prompting.

### Command-Line Options

- **Start a New Game**

  To start a new game and clear any existing game history, use the `--new` flag:

  ```bash
  php game.php --new
  ```

  - This will delete the `.game_history` file if it exists and start a fresh adventure.

- **Enable Debugging**

  For detailed debug logs, use the `--debug` flag:

  ```bash
  php game.php --debug
  ```

  - This will display internal processing messages, helpful for troubleshooting or understanding the game's operations.

- **Combine Flags**

  You can combine both flags as needed:

  ```bash
  php game.php --new --debug
  ```

---

## Gameplay Instructions

- **Game Start**

  After launching the game, you'll receive a welcome message:

  ```
  Welcome to 'The Quest of the Forgotten Realm'!
  (Type 'exit' or 'quit' to end the game at any time.)
  ```

- **Receiving Narration**

  The game will provide vivid descriptions of your surroundings, enriched with emojis to enhance visualization.

- **Making Choices**

  When prompted with `Your choice (1-4, or 'exit'):`, enter the number corresponding to your desired action and press **Enter**.

  - **Example:**
    ```
    [cyan]Your choice (1-4, or 'exit'): [/cyan] 2
    ```

- **Exiting the Game**

  At any prompt, type `exit` or `quit` to gracefully end your adventure.

---

## Security Considerations

- **API Key Storage**

  - Your OpenAI API key is stored in a hidden file named `.openai_api_key` in the project directory.
  - The file permissions are set to `0600`, making it readable and writable only by your user account.
  - **Do Not Share:** Never share your API key or commit it to any public repository.

- **Protecting Your API Key**

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

6. **API Schema Validation Errors**

   - **Error Message:** `Invalid schema for response_format 'game_scene': In context=('properties', 'options'), 'minItems' is not permitted.`
   - **Solution:** Ensure that the `minItems` and `maxItems` constraints are removed from the `response_format` schema in `game.php`. The latest refactored code provided above addresses this issue.

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

  - Modify parameters like `temperature` in the `$data` array to change response creativity.
    ```php
    'temperature' => 0.9,
    ```

- **Using a Different Model**

  - If you have access to GPT-4, you can change the model:
    ```php
    'model' => 'gpt-4',
    ```

- **Enhancing ASCII Art Quality**

  - Adjust the `$scale` variable in `ascii_art_converter.php` to change the resolution of the ASCII art.
  - Experiment with different character sets to improve shading and detail.

---

## License

This project is licensed under the [MIT License](LICENSE). You are free to use, modify, and distribute this software as per the license terms.

---

## Credits

- **Developer:** [Spencer Thayer](mailto:me@spencerthayer.com)
- **Contributor:** [nsbucky](mailto:kenrick@thebusypixel.com)
- **Inspiration:** Cascadia PHP 2024

Thank you to all the participants and organizers of Cascadia PHP 2024 for fostering a community of innovation and collaboration. Your support made this project possible!