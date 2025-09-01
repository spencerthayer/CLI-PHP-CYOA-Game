<?php

return [
    'paths' => [
        'api_key_file' => __DIR__ . '/../.data/.api_key', // Generic API key file
        'provider_config_file' => __DIR__ . '/../.data/.provider_config', // Store selected provider
        'game_history_file' => __DIR__ . '/../.data/.game_history',
        'user_prefs_file' => __DIR__ . '/../.data/.user_prefs',
        'debug_log_file' => __DIR__ . '/../.data/.debug_log',
        'images_dir' => __DIR__ . '/../images',
        'tmp_dir' => __DIR__ . '/../speech',
    ],
    
    // Provider configurations
    'providers' => [
        'openrouter' => [
            'name' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'chat_endpoint' => '/chat/completions',
            'models' => [
                // FREE Models with Function Support
                'google/gemini-2.5-flash-image-preview:free' => 'Gemini 2.5 Flash (🆓 FREE, Functions)',
                'openai/gpt-oss-120b:free' => 'GPT OSS 120B (🆓 FREE, Functions)',
                'openai/gpt-oss-20b:free' => 'GPT OSS 20B (🆓 FREE, Functions)',
                
                // FREE Models without Function Support
                'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => 'Venice: Uncensored (🆓 FREE)',
                'deepseek/deepseek-chat-v3.1:free' => 'DeepSeek Chat V3.1 (🆓 FREE)',
                
                // OpenAI Models via OpenRouter
                'openai/gpt-4o' => 'GPT-4o (OpenAI)',
                'openai/gpt-4o-mini' => 'GPT-4o Mini (OpenAI)',
                'openai/gpt-4-turbo' => 'GPT-4 Turbo (OpenAI)',
                'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo (OpenAI)',
                
                // Anthropic Models
                'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Most Capable)',
                'anthropic/claude-3-opus' => 'Claude 3 Opus (Creative)',
                'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet (Balanced)',
                'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast)',
                
                // Google Models - WITH Function Support
                'google/gemini-2.5-flash' => 'Gemini 2.5 Flash (💰 Affordable, Functions)',
                'google/gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite (💰 Super Cheap)',
                'google/gemini-pro-1.5' => 'Gemini Pro 1.5 (Google)',
                'google/gemini-flash-1.5' => 'Gemini Flash 1.5 (Fast)',
                
                // Meta Models
                'meta-llama/llama-3.1-405b-instruct' => 'Llama 3.1 405B (Meta)',
                'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B (Meta)',
                
                // Mistral Models
                'mistralai/mistral-large' => 'Mistral Large',
                'mistralai/mixtral-8x7b-instruct' => 'Mixtral 8x7B',
                
                // Nous Research
                'nousresearch/hermes-3-llama-3.1-405b' => 'Hermes 3 405B',
                
                // DeepSeek
                'deepseek/deepseek-chat' => 'DeepSeek Chat',
                
                // Qwen
                'qwen/qwen-2.5-72b-instruct' => 'Qwen 2.5 72B',
                
                // xAI
                'x-ai/grok-beta' => 'Grok Beta (xAI)',
            ],
            'default_model' => 'google/gemini-2.5-flash-image-preview:free',
            'supports_functions' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer {API_KEY}',
                'HTTP-Referer' => 'https://github.com/the-dying-earth-cli', // Optional: for rankings
                'X-Title' => 'The Dying Earth CLI Game' // Optional: for app attribution
            ],
            'extra_body' => [
                // Optional parameters for OpenRouter
                'provider' => [
                    'order' => [], // Can specify provider preference
                    'allow_fallbacks' => true
                ]
            ]
        ]
    ],
    
    // Default API settings (provider-agnostic)
    'api' => [
        'max_tokens' => 1280,
        'temperature' => 1.0,
        'max_image_description_length' => 64,
        'timeout' => 30, // API timeout in seconds
        'retry_attempts' => 3,
        'retry_delay' => 2, // Delay between retries in seconds
    ],
    
    'game' => [
        'max_iterations' => 1280,
        'max_custom_action_length' => 512,
        'generate_image_toggle' => true,
        'audio_toggle' => false,
    ],
    
    // Image generation settings
    'image' => [
        'scale' => 4, // Scale factor for ASCII art conversion
        'max_width' => 100, // Maximum width for ASCII art
        'max_height' => 30, // Maximum height for ASCII art
        
        // Image generation service configuration
        'generation_service' => 'pollinations', // Options: 'openrouter', 'pollinations', 'both'
        'prefer_openrouter' => false, // Gemini only generates square images (1:1), not suitable for our needs
        
        // OpenRouter settings - Gemini 2.5 Flash Image Preview (LIMITATION: Only generates 1:1 square images)
        'openrouter' => [
            'model' => 'google/gemini-2.5-flash-image-preview:free',
            'enabled' => false, // Disabled due to square-only limitation
            'timeout' => 30,
            // Would cost $0.039 per image but doesn't support custom aspect ratios
        ],
        
        // Pollinations settings (free and supports 16:9!) - Primary service
        'pollinations' => [
            'model' => 'turbo', // 'turbo' (fast, reliable) or 'flux' (better but often down)
            'width' => 640,  // 16:9 aspect ratio - WORKS!
            'height' => 360, // 16:9 aspect ratio - WORKS!
            'timeout' => 20,
            'fallback_on_failure' => true, // Fall back to no image if generation fails
        ],
    ],
    
    'audio' => [
        'voice' => 'ash',
        'model' => 'openai-audio',
        'max_text_length' => 1280,
    ],
    
    'weights' => [
        'edge' => 0.4,
        'variance' => 0.4,
        'gradient' => 0.8,
        'intensity' => 0.2
    ],
    
    'difficulty_range' => [
        'min' => 4,
        'max' => 18
    ],
    
    'region_size' => 4,
    'random_factor' => 0.05,
    'system_prompt' =>
        "You are an interactive text-based adventure game called **'The Dying Earth.'**

        This is a grimdark world set in the twilight of civilization on a world immeasurably aged, built upon the enigmatic remnants of societies so ancient their very names have been erased by time. The sun wanes in the sky, casting a spectral light over landscapes where the boundaries between magic and technology blur, and wonders from bygone eras lie half-buried and waiting. The prose should evoke the opulent and elaborate style of **Jack Vance**, enriched by the mystique of **Monte Cook's Numenera**, weaving together a tapestry of exotic locales, peculiar customs, and the haunting beauty of a world layered with history.

        Guide the player through this layered realm with language that captures the wonder, strangeness, and cosmic horror of a world built upon forgotten epochs. Every choice should feel like a step into deeper enigmas—a path into further darkness where knowledge and power await those willing to delve into the depths of time, but not without potential peril.

        Always provide **exactly four options** for the player to choose from. Each option MUST include a skill check in the format [Attribute DC:difficulty], where:
        - Attribute is one of: Agility, Appearance, Charisma, Dexterity, Endurance, Intellect, Knowledge, Luck, Perception, Spirit, Strength, Vitality, Willpower, Wisdom
        - DC (Difficulty Class) is a number between 4 and 18, representing the challenge's difficulty
        - Example: [Wisdom DC:12] for discerning ancient knowledge
        
        Each option should be a single, elegantly crafted sentence that includes both the skill check and 1-2 relevant, thematic emojis. Format each option like this:
        '🔍 Examine the ancient runes etched into the archway [Intellect DC:12]'

        When a skill check has been made:
        - For SUCCESS: Describe the character accomplishing their goal, possibly with additional benefits
        - For FAILURE: Describe setbacks or complications, but avoid outright killing the character

        Very easy checks (DC 4-7)
        Moderate challenges (DC 8-12)
        Difficult challenges (DC 13-16)
        Nearly impossible feats (DC 17-18)

### **Physical Attributes**

- **Strength**: Physical power, determining melee damage output and ability to lift or move heavy objects.
- **Agility**: Quickness and coordination in movement, affecting dodging and acrobatic maneuvers.
- **Dexterity**: Fine motor skills and hand-eye coordination, influencing precision tasks like lock-picking and ranged attacks.
- **Endurance**: Stamina and physical resilience, determining how long you can exert yourself and resist fatigue.
- **Vitality**: Physical health and resistance, influencing overall hit points, healing rate, and resistance to illnesses or toxins.

### **Mental Attributes**

- **Intellect**: Mental acuity and reasoning ability, affecting problem-solving and learning speed.
- **Wisdom**: Sound judgment, experience, and decision-making skills, impacting insight, strategic planning, and possibly certain types of magic or abilities.
- **Knowledge**: Accumulated information and expertise, determining if a character knows specific facts or skills.
- **Willpower**: Mental fortitude and focus, affecting resistance to mental attacks, ability to concentrate under pressure, and sustain magical abilities.
- **Perception**: Awareness of surroundings and ability to notice hidden details or dangers, enhancing detection and scouting abilities.

### **Social Attributes**

- **Charisma**: Social influence and persuasion, impacting interactions, negotiations, and leadership roles.
- **Appearance**: Physical attractiveness, influencing first impressions and certain social interactions.

### **Soul Attributes**

- **Spirit**: Spiritual connection, affecting magical abilities, resistance to spiritual or elemental effects, and interactions with mystical entities.
- **Luck**: Fortune and discovery, influencing random events, critical successes, and item finds.",
]; 