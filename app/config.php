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
                'google/gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite (💰 3x Cheaper!, Functions)',
                'google/gemini-2.5-flash' => 'Gemini 2.5 Flash (💰 Affordable, Functions)',
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
            'default_model' => 'google/gemini-2.5-flash-lite',
            'supports_functions' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer {API_KEY}',
                'HTTP-Referer' => 'https://github.com/spencerthayer/CLI-PHP-CYOA-Game', // Optional: for rankings
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
        'max_image_description_length' => 256,
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
        'max_height' => 1001, // Maximum height for ASCII art
        
        // Image generation service configuration
        'generation_service' => 'openrouter', // Using OpenRouter for image generation
        'prefer_openrouter' => true,
        
        // OpenRouter settings - Using Gemini Flash for fastest generation
        'openrouter' => [
            'model' => 'google/gemini-2.5-flash-image-preview', // Fast image generation model
            'enabled' => true,
            'timeout' => 60,
            'aspect_ratio' => '16:9', // 1344×768 pixels
            'image_size' => '1K',     // Standard resolution for speed
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

        Always provide **exactly four UNIQUE options** for the player to choose from.
        
        CRITICAL FORMAT REQUIREMENTS:
        - ALL FOUR OPTIONS MUST BE DIFFERENT - never repeat the same action
        - Options are returned as objects with three fields: 'emoji', 'text', and 'skill_check'
        - DO NOT include numbers - the game adds them automatically
        - DO NOT combine emoji with text - they must be separate fields
        - Each option should offer a distinct action or approach
        
        Example option object:
        {
            \"emoji\": \"🔍\",
            \"text\": \"Examine the ancient runes etched into the archway\",
            \"skill_check\": \"[Intellect DC:12]\"
        }
        
        VALIDATION: Before returning options, verify:
        - No two options have the same text (even with different skill checks)
        - No two options describe the same action
        - All four emojis should be different when possible
        
        Skill check format: [Attribute DC:difficulty]
        - Attribute is one of: Agility, Appearance, Charisma, Dexterity, Endurance, Intellect, Knowledge, Luck, Perception, Spirit, Strength, Vitality, Willpower, Wisdom
        - DC (Difficulty Class) is a number between 4 and 18
        
        Generate unique, contextually appropriate actions for each scene.
        
        OPTION VARIETY REQUIREMENTS:
        - Each option must represent a DIFFERENT approach or action
        - Include varied skill checks using different attributes
        - Mix action types: examine, move, interact, sense, communicate, etc.
        - Vary difficulty levels across the four options
        - NEVER duplicate an option with just different wording
        
        IMPORTANT - Action Results Must Be Specific:
        - 'Examine X' SUCCESS = Describe what is found/read/discovered on X
        - 'Search for Y' SUCCESS = Reveal what Y is found or what is discovered instead  
        - 'Listen for sounds' SUCCESS = Describe the specific sounds heard
        - 'Walk to location' SUCCESS = Arrive at location and describe NEW scene
        - 'Try to understand X' SUCCESS = Explain what X means/does/reveals
        
        Never just say 'you successfully examine it' - always reveal WHAT was discovered!

        When a skill check has been made:
        
        SUCCESS/FAILURE GRADIENT:
        The margin between the roll and DC determines severity:
        - CRITICAL SUCCESS (margin +10 or more): Exceptional achievement beyond expectations
        - GREAT SUCCESS (margin +5 to +9): Solid success with bonus benefits
        - SUCCESS (margin 0 to +4): Narrow success, just making it work
        - FAILURE (margin -1 to -4): Close failure with minor consequences
        - MAJOR FAILURE (margin -5 to -9): Significant failure with clear setbacks
        - CRITICAL FAILURE (margin -10 or worse): Catastrophic failure with terrible consequences
        
        IMPORTANT: When you receive a skill check result, the narrative MUST match the outcome:
        - If marked SUCCESS: The action succeeds - write it succeeding WITH CONCRETE RESULTS
        - If marked FAILURE: The action fails - write it failing WITH CONSEQUENCES
        - Use the margin/severity to determine HOW WELL it succeeds or HOW BADLY it fails
        
        CRITICAL RULE - Story Progression After Skill Checks:
        
        FOR SUCCESSFUL ACTIONS:
        - REVEAL what the player discovered/achieved (e.g., if examining inscriptions, describe WHAT IS WRITTEN)
        - PROGRESS the story based on that discovery (don't just re-describe the same scene)
        - OFFER NEW OPTIONS based on what was just learned or accomplished
        - DO NOT repeat the same scene description with different words
        - Each success should unlock new information, paths, or possibilities
        
        FOR FAILED ACTIONS:
        - Show the consequences of failure (injury, setback, complication)
        - DO NOT present the same challenge again
        - Provide alternative paths forward or different approaches
        - Build upon the failure to create new narrative opportunities
        
        NEVER loop back to the same scene description after an action is taken!
        Track what has been attempted and discovered to ensure continuous forward progression
        
        Difficulty Guidelines:
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