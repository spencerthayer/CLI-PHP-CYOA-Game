# The Quest of the Forgotten Realm

Welcome to **The Quest of the Forgotten Realm**, an interactive text-based adventure game created during **Cascadia PHP 2024**. This project leverages OpenAI's ChatGPT and DALL·E 2 APIs to deliver a dynamic and immersive storytelling experience directly in your command-line interface (CLI). A heartfelt thank you to the organizers and participants of Cascadia PHP 2024 for inspiring and supporting this project!

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage](#usage)
  - [Starting the Game](#starting-the-game)
  - [Command-Line Options](#command-line-options)
- [Gameplay Instructions](#gameplay-instructions)
- [Character Stats Explained](#character-stats-explained)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)
- [Customization](#customization)
- [License](#license)
- [Credits](#credits)

---

## Animated Loading Spinner for Image and Audio Generation

This project now features a concurrent animated loading spinner for both image and audio generation requests. The spinner provides immediate, responsive feedback to the user while waiting for API responses from Pollinations.ai.

### How It Works
- When generating images or audio, the spinner animation starts **immediately before** the API call and stops as soon as the response is received.
- The spinner runs as a background process (`app/SpinnerProcess.php`), allowing it to animate independently of blocking operations.
- The spinner message adapts to the context: "Generating image" or "Generating audio".

### Relevant Files
- `app/Utils.php` — Utility functions for starting/stopping the spinner with `showLoadingAnimation` and `stopLoadingAnimation`.
- `app/SpinnerProcess.php` — The background process that displays the spinner.
- `app/ImageHandler.php` & `app/AudioHandler.php` — Updated to start the spinner before and stop it after each API request.

### Example Usage (Code Snippet)
```php
$loading_pid = Utils::showLoadingAnimation('image'); // or 'audio'
// ... make API request ...
Utils::stopLoadingAnimation($loading_pid);
```

### Why This Matters
- **Improved UX:** Users see instant feedback when waiting for image or audio generation.
- **Accurate Timing:** The spinner reflects the actual API wait time, not just a fixed delay.
- **Non-blocking:** The game remains responsive while the spinner animates.

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
   - Ensure the `readline` extension is enabled for interactive input. Check with `php -m | grep readline`.

4. **Create Necessary Directories**

   The game will automatically create an `images` directory for temporary image storage. Ensure the PHP process has permissions to create directories in the project path.
   - The game also uses a `data` directory to store the API key, game history, and debug logs. Ensure the PHP process has write permissions in the project's root directory to create this if it doesn't exist.

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
  - The API key will be saved securely in a hidden file named `data/.openai_api_key` with permissions set to `0600` (readable and writable only by you).

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

  - This will display internal processing messages in the console and write detailed logs to `data/debug_log.txt`, helpful for troubleshooting or understanding the game's operations.

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

  - Some choices may involve hidden **skill checks**, **saving throws**, or **sanity checks** based on your character's stats. The outcome of these checks (Success/Failure) might alter the available options or narrative results.

  - **Other Commands:**
    - `t`: Type in a custom action instead of choosing a numbered option.
    - `s`: Display your character sheet, showing stats and attributes.
    - `g`: Toggle the generation of ASCII art images for scenes (On/Off).
    - `n`: Start a new game (you will be asked for confirmation).
    - `q` or `quit`: Exit the game.

  - **Example:**
    ```
    [cyan]Your choice (1-4, or 'exit'): [/cyan] 2
    ```

- **Exiting the Game**

  At any prompt, type `exit` or `quit` to gracefully end your adventure.

---

## Character Stats Explained

Your character possesses a set of attributes that influence their capabilities and resilience within the game world. These are divided into Primary Attributes and Derived Stats.

### Primary Attributes

These 14 core attributes are randomly determined at the start of a new game (typically between 8 and 18) and represent your character's innate abilities:

*   **Agility:** Physical dexterity, speed, and reflexes.
*   **Appearance:** Physical attractiveness and grooming.
*   **Charisma:** Social grace, likability, and leadership potential.
*   **Dexterity:** Hand-eye coordination, fine motor skills, and nimbleness.
*   **Endurance:** Physical stamina, resilience to fatigue and pain.
*   **Intellect:** Reasoning ability, memory, and problem-solving skills.
*   **Knowledge:** Learned information and expertise.
*   **Luck:** Innate fortune and chance.
*   **Perception:** Awareness of surroundings, ability to notice details.
*   **Spirit:** Inner strength, morale, and connection to mystical forces.
*   **Strength:** Physical power and brute force.
*   **Vitality:** Overall health, constitution, and resistance to disease/poison.
*   **Willpower:** Mental fortitude, self-control, and determination.
*   **Wisdom:** Intuition, common sense, and insight.

Each Primary Attribute score provides a **Modifier**, calculated as `floor((Score - 10) / 2)`. This modifier is added to relevant dice rolls (d20) during Skill Checks and Saving Throws.

### Derived Stats

These stats are calculated based on your Primary Attributes and represent your character's resources and current state:

*   **Health:** Hit points; resistance to physical damage. Influenced by `Vitality` and `Endurance`.
    *   *Formula: floor((Vitality * 2) + Endurance)*
*   **Focus:** Mental energy for special abilities or concentration. Influenced by `Willpower`, `Intellect`, and `Wisdom`.
    *   *Formula: floor(Willpower + ((Intellect + Wisdom) / 2))* 
*   **Stamina:** Physical energy for strenuous actions. Influenced by `Endurance`, `Strength`, and `Agility`.
    *   *Formula: floor((Endurance * 1.5) + ((Strength + Agility) / 2))*
*   **Courage:** Resistance to fear and intimidation. Influenced by `Willpower`, `Spirit`, and `Charisma`.
    *   *Formula: floor(Willpower + ((Spirit + Charisma) / 2))*
*   **Sanity:** Mental stability and resistance to psychological stress. Influenced by `Willpower`, `Intellect`, and `Perception`.
    *   *Formula: floor((Willpower * 1.5) + ((Intellect + Perception) / 2))*

### Skill Checks, Saving Throws, and Sanity Checks

Many actions you attempt or situations you encounter will trigger a check or save:

*   **Skill Check:** When attempting an action requiring a specific ability (e.g., climbing a wall using Strength), you roll a d20, add the relevant attribute's modifier (and potentially a proficiency bonus), and compare it to a Difficulty Class (DC). *Format: `[SKILL_CHECK:Attribute:DC]`*
*   **Saving Throw:** When resisting an external effect (e.g., dodging a trap using Dexterity), you roll a d20, add the relevant attribute's modifier, and compare it to a DC. Specific saves use fixed attributes:
    *   Fortitude: `Vitality`
    *   Reflex: `Dexterity`
    *   Will: `Willpower`
    *   Social: `Charisma`
    *   *Format: `[SAVE:SaveType:DC]`*
*   **Sanity Check:** A special check against mental strain, rolling d20 plus your Sanity modifier against a DC. Failing can result in a loss of Sanity points. *Format: `[SANITY_CHECK:DC]`*

Success or failure in these rolls determines the outcome of your actions and how the narrative progresses.

---

## Security Considerations

- **API Key Storage**

  - Your OpenAI API key is stored in a hidden file named `data/.openai_api_key` in the project directory.
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

  - Open `app/config.php` in a text editor.
  - Modify the `system_prompt` key within the returned array to change the game's setting or rules.
    ```php
    'system_prompt' => "You are an interactive sci-fi adventure game set in space...",
    ```

- **Adjusting API Parameters**

  - In `app/config.php`, modify parameters like `temperature` under the `api` key to change response creativity.
    ```php
    'api' => [
        // ... other settings
        'temperature' => 0.9,
        // ...
    ],
    ```

- **Using a Different Model**

  - In `app/config.php`, change the `model` under the `api` key:
    ```php
    'api' => [
        // ... other settings
        'model' => 'gpt-4', // Or another available model
        // ...
    ],
    ```

- **Enhancing ASCII Art Quality**

  - Adjust the `scale` parameter under the `image` key in `app/config.php` to change the resolution of the ASCII art.
  - You can also experiment with different character sets in `app/AsciiArtConverter.php`.

- **Customizing Audio Settings**

  You can configure the voice and maximum text length for audio generation by editing the `audio` section in `app/config.php`:

  ```php
  'audio' => [
      'voice' => 'ash',            // Change to your preferred voice
      'model' => 'openai-audio',   // Audio model used
      'max_text_length' => 5120,   // Maximum characters per audio request
  ],
  ```

  - **voice**: The default voice for text-to-speech (e.g., 'ash').
  - **max_text_length**: The maximum number of characters per audio generation request. If your narration is longer, it will be split into chunks.

  Any changes here will automatically be used by the game's audio system.

---

## License

This project is licensed under the [MIT License](LICENSE). You are free to use, modify, and distribute this software as per the license terms.

---

## Credits

- **Developer:** [Spencer Thayer](mailto:me@spencerthayer.com)
- **Contributor:** [nsbucky](mailto:kenrick@thebusypixel.com)
- **Inspiration:** Cascadia PHP 2024

Thank you to all the participants and organizers of Cascadia PHP 2024 for fostering a community of innovation and collaboration. Your support made this project possible!