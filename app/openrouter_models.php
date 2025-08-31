<?php

/**
 * OpenRouter Models Configuration
 * Generated on: 2025-08-31 18:36:09
 * Total models: 5
 */

return [
    // Popular models for quick selection
    'popular' => [
        'openai/gpt-4o' => 'GPT-4o (Most capable)',
        'openai/gpt-4o-mini' => 'GPT-4o Mini (Fast & affordable)',
        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Best overall)',
        'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & cheap)',
        'google/gemini-pro-1.5' => 'Gemini Pro 1.5',
        'meta-llama/llama-3.1-405b-instruct' => 'Llama 3.1 405B',
        'mistralai/mistral-large' => 'Mistral Large',
        'deepseek/deepseek-chat' => 'DeepSeek Chat',
    ],

    // All models grouped by provider
    'all' => [
        // Openai Models
        'openai/chatgpt-4o-latest' => 'OpenAI: ChatGPT-4o (128k context, Vision)',
        'openai/codex-mini' => 'OpenAI: Codex Mini (200k context, Affordable, Vision, Functions)',
        'openai/gpt-3.5-turbo' => 'OpenAI: GPT-3.5 Turbo (Affordable, Functions)',
        'openai/gpt-3.5-turbo-0613' => 'OpenAI: GPT-3.5 Turbo (older v0613) (Affordable, Functions)',
        'openai/gpt-3.5-turbo-16k' => 'OpenAI: GPT-3.5 Turbo 16k (Functions)',
        'openai/gpt-3.5-turbo-instruct' => 'OpenAI: GPT-3.5 Turbo Instruct (Affordable)',
        'openai/gpt-4' => 'OpenAI: GPT-4 (Functions)',
        'openai/gpt-4-0314' => 'OpenAI: GPT-4 (older v0314) (Functions)',
        'openai/gpt-4-1106-preview' => 'OpenAI: GPT-4 Turbo (older v1106) (128k context, Functions)',
        'openai/gpt-4-turbo' => 'OpenAI: GPT-4 Turbo (128k context, Vision, Functions)',
        'openai/gpt-4-turbo-preview' => 'OpenAI: GPT-4 Turbo Preview (128k context, Functions)',
        'openai/gpt-4.1' => 'OpenAI: GPT-4.1 (1048k context, Vision, Functions)',
        'openai/gpt-4.1-mini' => 'OpenAI: GPT-4.1 Mini (1048k context, Very cheap, Vision, Functions)',
        'openai/gpt-4.1-nano' => 'OpenAI: GPT-4.1 Nano (1048k context, Very cheap, Vision, Functions)',
        'openai/gpt-4o' => 'OpenAI: GPT-4o (128k context, Vision, Functions)',
        'openai/gpt-4o-2024-05-13' => 'OpenAI: GPT-4o (2024-05-13) (128k context, Vision, Functions)',
        'openai/gpt-4o-2024-08-06' => 'OpenAI: GPT-4o (2024-08-06) (128k context, Vision, Functions)',
        'openai/gpt-4o-2024-11-20' => 'OpenAI: GPT-4o (2024-11-20) (128k context, Vision, Functions)',
        'openai/gpt-4o-audio-preview' => 'OpenAI: GPT-4o Audio (128k context, Functions)',
        'openai/gpt-4o-mini' => 'OpenAI: GPT-4o-mini (128k context, Very cheap, Vision, Functions)',
        'openai/gpt-4o-mini-2024-07-18' => 'OpenAI: GPT-4o-mini (2024-07-18) (128k context, Very cheap, Vision, Functions)',
        'openai/gpt-4o-mini-search-preview' => 'OpenAI: GPT-4o-mini Search Preview (128k context, Very cheap)',
        'openai/gpt-4o-search-preview' => 'OpenAI: GPT-4o Search Preview (128k context)',
        'openai/gpt-4o:extended' => 'OpenAI: GPT-4o (extended) (128k context, Vision, Functions)',
        'openai/gpt-5' => 'OpenAI: GPT-5 (400k context, Affordable, Vision, Functions)',
        'openai/gpt-5-chat' => 'OpenAI: GPT-5 Chat (128k context, Affordable, Vision)',
        'openai/gpt-5-mini' => 'OpenAI: GPT-5 Mini (400k context, Very cheap, Vision, Functions)',
        'openai/gpt-5-nano' => 'OpenAI: GPT-5 Nano (400k context, Very cheap, Vision, Functions)',
        'openai/gpt-oss-120b' => 'OpenAI: gpt-oss-120b (131k context, Very cheap, Functions)',
        'openai/gpt-oss-120b:free' => 'OpenAI: gpt-oss-120b (free) (Free)',
        'openai/gpt-oss-20b' => 'OpenAI: gpt-oss-20b (131k context, Very cheap, Functions)',
        'openai/gpt-oss-20b:free' => 'OpenAI: gpt-oss-20b (free) (131k context, Free)',
        'openai/o1' => 'OpenAI: o1 (200k context, Vision, Functions)',
        'openai/o1-mini' => 'OpenAI: o1-mini (128k context, Affordable)',
        'openai/o1-mini-2024-09-12' => 'OpenAI: o1-mini (2024-09-12) (128k context, Affordable)',
        'openai/o1-pro' => 'OpenAI: o1-pro (200k context, Vision)',
        'openai/o3' => 'OpenAI: o3 (200k context, Vision, Functions)',
        'openai/o3-mini' => 'OpenAI: o3 Mini (200k context, Affordable, Functions)',
        'openai/o3-mini-high' => 'OpenAI: o3 Mini High (200k context, Affordable, Functions)',
        'openai/o3-pro' => 'OpenAI: o3 Pro (200k context, Vision, Functions)',
        'openai/o4-mini' => 'OpenAI: o4 Mini (200k context, Affordable, Vision, Functions)',
        'openai/o4-mini-high' => 'OpenAI: o4 Mini High (200k context, Affordable, Vision, Functions)',

        // Anthropic Models
        'anthropic/claude-3-haiku' => 'Anthropic: Claude 3 Haiku (200k context, Very cheap, Vision, Functions)',
        'anthropic/claude-3-opus' => 'Anthropic: Claude 3 Opus (200k context, Vision, Functions)',
        'anthropic/claude-3.5-haiku' => 'Anthropic: Claude 3.5 Haiku (200k context, Affordable, Vision, Functions)',
        'anthropic/claude-3.5-haiku-20241022' => 'Anthropic: Claude 3.5 Haiku (2024-10-22) (200k context, Affordable, Vision, Functions)',
        'anthropic/claude-3.5-sonnet' => 'Anthropic: Claude 3.5 Sonnet (200k context, Vision, Functions)',
        'anthropic/claude-3.5-sonnet-20240620' => 'Anthropic: Claude 3.5 Sonnet (2024-06-20) (200k context, Vision, Functions)',
        'anthropic/claude-3.7-sonnet' => 'Anthropic: Claude 3.7 Sonnet (200k context, Vision, Functions)',
        'anthropic/claude-3.7-sonnet:thinking' => 'Anthropic: Claude 3.7 Sonnet (thinking) (200k context, Vision, Functions)',
        'anthropic/claude-opus-4' => 'Anthropic: Claude Opus 4 (200k context, Vision, Functions)',
        'anthropic/claude-opus-4.1' => 'Anthropic: Claude Opus 4.1 (200k context, Vision, Functions)',
        'anthropic/claude-sonnet-4' => 'Anthropic: Claude Sonnet 4 (1000k context, Vision, Functions)',

        // Google Models
        'google/gemini-2.0-flash-001' => 'Google: Gemini 2.0 Flash (1049k context, Very cheap, Vision, Functions)',
        'google/gemini-2.0-flash-exp:free' => 'Google: Gemini 2.0 Flash Experimental (free) (1049k context, Free, Vision, Functions)',
        'google/gemini-2.0-flash-lite-001' => 'Google: Gemini 2.0 Flash Lite (1049k context, Very cheap, Vision, Functions)',
        'google/gemini-2.5-flash' => 'Google: Gemini 2.5 Flash (1049k context, Very cheap, Vision, Functions)',
        'google/gemini-2.5-flash-image-preview' => 'Google: Gemini 2.5 Flash Image Preview (Very cheap, Vision)',
        'google/gemini-2.5-flash-image-preview:free' => 'Google: Gemini 2.5 Flash Image Preview (free) (Free, Vision)',
        'google/gemini-2.5-flash-lite' => 'Google: Gemini 2.5 Flash Lite (1049k context, Very cheap, Vision, Functions)',
        'google/gemini-2.5-flash-lite-preview-06-17' => 'Google: Gemini 2.5 Flash Lite Preview 06-17 (1049k context, Very cheap, Vision, Functions)',
        'google/gemini-2.5-pro' => 'Google: Gemini 2.5 Pro (1049k context, Affordable, Vision, Functions)',
        'google/gemini-2.5-pro-exp-03-25' => 'Google: Gemini 2.5 Pro Experimental (1049k context, Free, Vision, Functions)',
        'google/gemini-2.5-pro-preview' => 'Google: Gemini 2.5 Pro Preview 06-05 (1049k context, Affordable, Vision, Functions)',
        'google/gemini-2.5-pro-preview-05-06' => 'Google: Gemini 2.5 Pro Preview 05-06 (1049k context, Affordable, Vision, Functions)',
        'google/gemini-flash-1.5' => 'Google: Gemini 1.5 Flash  (1000k context, Very cheap, Vision, Functions)',
        'google/gemini-flash-1.5-8b' => 'Google: Gemini 1.5 Flash 8B (1000k context, Very cheap, Vision, Functions)',
        'google/gemini-pro-1.5' => 'Google: Gemini 1.5 Pro (2000k context, Affordable, Vision, Functions)',
        'google/gemma-2-27b-it' => 'Google: Gemma 2 27B (Affordable)',
        'google/gemma-2-9b-it' => 'Google: Gemma 2 9B (Very cheap)',
        'google/gemma-2-9b-it:free' => 'Google: Gemma 2 9B (free) (Free)',
        'google/gemma-3-12b-it' => 'Google: Gemma 3 12B (Very cheap, Vision)',
        'google/gemma-3-12b-it:free' => 'Google: Gemma 3 12B (free) (Free, Vision)',
        'google/gemma-3-27b-it' => 'Google: Gemma 3 27B (Very cheap, Vision)',
        'google/gemma-3-27b-it:free' => 'Google: Gemma 3 27B (free) (Free, Vision)',
        'google/gemma-3-4b-it' => 'Google: Gemma 3 4B (131k context, Very cheap, Vision)',
        'google/gemma-3-4b-it:free' => 'Google: Gemma 3 4B (free) (Free, Vision)',
        'google/gemma-3n-e2b-it:free' => 'Google: Gemma 3n 2B (free) (Free)',
        'google/gemma-3n-e4b-it' => 'Google: Gemma 3n 4B (Very cheap)',
        'google/gemma-3n-e4b-it:free' => 'Google: Gemma 3n 4B (free) (Free)',

        // Meta-llama Models
        'meta-llama/llama-3-70b-instruct' => 'Meta: Llama 3 70B Instruct (Very cheap, Functions)',
        'meta-llama/llama-3-8b-instruct' => 'Meta: Llama 3 8B Instruct (Very cheap, Functions)',
        'meta-llama/llama-3.1-405b' => 'Meta: Llama 3.1 405B (base)',
        'meta-llama/llama-3.1-405b-instruct' => 'Meta: Llama 3.1 405B Instruct (Affordable, Functions)',
        'meta-llama/llama-3.1-405b-instruct:free' => 'Meta: Llama 3.1 405B Instruct (free) (Free)',
        'meta-llama/llama-3.1-70b-instruct' => 'Meta: Llama 3.1 70B Instruct (131k context, Very cheap, Functions)',
        'meta-llama/llama-3.1-8b-instruct' => 'Meta: Llama 3.1 8B Instruct (131k context, Very cheap, Functions)',
        'meta-llama/llama-3.2-11b-vision-instruct' => 'Meta: Llama 3.2 11B Vision Instruct (131k context, Very cheap, Vision)',
        'meta-llama/llama-3.2-1b-instruct' => 'Meta: Llama 3.2 1B Instruct (131k context, Very cheap)',
        'meta-llama/llama-3.2-3b-instruct' => 'Meta: Llama 3.2 3B Instruct (Very cheap, Functions)',
        'meta-llama/llama-3.2-3b-instruct:free' => 'Meta: Llama 3.2 3B Instruct (free) (131k context, Free)',
        'meta-llama/llama-3.2-90b-vision-instruct' => 'Meta: Llama 3.2 90B Vision Instruct (Very cheap, Vision)',
        'meta-llama/llama-3.3-70b-instruct' => 'Meta: Llama 3.3 70B Instruct (131k context, Very cheap, Functions)',
        'meta-llama/llama-3.3-70b-instruct:free' => 'Meta: Llama 3.3 70B Instruct (free) (Free, Functions)',
        'meta-llama/llama-3.3-8b-instruct:free' => 'Meta: Llama 3.3 8B Instruct (free) (128k context, Free, Functions)',
        'meta-llama/llama-4-maverick' => 'Meta: Llama 4 Maverick (1049k context, Very cheap, Vision, Functions)',
        'meta-llama/llama-4-maverick:free' => 'Meta: Llama 4 Maverick (free) (128k context, Free, Vision, Functions)',
        'meta-llama/llama-4-scout' => 'Meta: Llama 4 Scout (1049k context, Very cheap, Vision, Functions)',
        'meta-llama/llama-4-scout:free' => 'Meta: Llama 4 Scout (free) (128k context, Free, Vision, Functions)',
        'meta-llama/llama-guard-2-8b' => 'Meta: LlamaGuard 2 8B (Very cheap)',
        'meta-llama/llama-guard-3-8b' => 'Llama Guard 3 8B (131k context, Very cheap)',
        'meta-llama/llama-guard-4-12b' => 'Meta: Llama Guard 4 12B (164k context, Very cheap, Vision)',

        // Mistralai Models
        'mistralai/codestral-2501' => 'Mistral: Codestral 2501 (262k context, Very cheap, Functions)',
        'mistralai/codestral-2508' => 'Mistral: Codestral 2508 (256k context, Very cheap, Functions)',
        'mistralai/devstral-medium' => 'Mistral: Devstral Medium (131k context, Very cheap, Functions)',
        'mistralai/devstral-small' => 'Mistral: Devstral Small 1.1 (128k context, Very cheap, Functions)',
        'mistralai/devstral-small-2505' => 'Mistral: Devstral Small 2505 (131k context, Very cheap, Functions)',
        'mistralai/devstral-small-2505:free' => 'Mistral: Devstral Small 2505 (free) (Free, Functions)',
        'mistralai/magistral-medium-2506' => 'Mistral: Magistral Medium 2506 (Functions)',
        'mistralai/magistral-medium-2506:thinking' => 'Mistral: Magistral Medium 2506 (thinking) (Functions)',
        'mistralai/magistral-small-2506' => 'Mistral: Magistral Small 2506 (Affordable, Functions)',
        'mistralai/ministral-3b' => 'Mistral: Ministral 3B (Very cheap)',
        'mistralai/ministral-8b' => 'Mistral: Ministral 8B (128k context, Very cheap, Functions)',
        'mistralai/mistral-7b-instruct' => 'Mistral: Mistral 7B Instruct (Very cheap, Functions)',
        'mistralai/mistral-7b-instruct-v0.1' => 'Mistral: Mistral 7B Instruct v0.1 (Very cheap, Functions)',
        'mistralai/mistral-7b-instruct-v0.3' => 'Mistral: Mistral 7B Instruct v0.3 (Very cheap, Functions)',
        'mistralai/mistral-7b-instruct:free' => 'Mistral: Mistral 7B Instruct (free) (Free, Functions)',
        'mistralai/mistral-large' => 'Mistral Large (128k context, Functions)',
        'mistralai/mistral-large-2407' => 'Mistral Large 2407 (131k context, Functions)',
        'mistralai/mistral-large-2411' => 'Mistral Large 2411 (131k context, Functions)',
        'mistralai/mistral-medium-3' => 'Mistral: Mistral Medium 3 (131k context, Very cheap, Vision, Functions)',
        'mistralai/mistral-medium-3.1' => 'Mistral: Mistral Medium 3.1 (131k context, Very cheap, Vision, Functions)',
        'mistralai/mistral-nemo' => 'Mistral: Mistral Nemo (Very cheap, Functions)',
        'mistralai/mistral-nemo:free' => 'Mistral: Mistral Nemo (free) (131k context, Free)',
        'mistralai/mistral-saba' => 'Mistral: Saba (Very cheap, Functions)',
        'mistralai/mistral-small' => 'Mistral Small (Very cheap, Functions)',
        'mistralai/mistral-small-24b-instruct-2501' => 'Mistral: Mistral Small 3 (Very cheap, Functions)',
        'mistralai/mistral-small-24b-instruct-2501:free' => 'Mistral: Mistral Small 3 (free) (Free)',
        'mistralai/mistral-small-3.1-24b-instruct' => 'Mistral: Mistral Small 3.1 24B (131k context, Very cheap, Vision, Functions)',
        'mistralai/mistral-small-3.1-24b-instruct:free' => 'Mistral: Mistral Small 3.1 24B (free) (128k context, Free, Vision, Functions)',
        'mistralai/mistral-small-3.2-24b-instruct' => 'Mistral: Mistral Small 3.2 24B (128k context, Very cheap, Vision, Functions)',
        'mistralai/mistral-small-3.2-24b-instruct:free' => 'Mistral: Mistral Small 3.2 24B (free) (131k context, Free, Vision, Functions)',
        'mistralai/mistral-tiny' => 'Mistral Tiny (Very cheap, Functions)',
        'mistralai/mixtral-8x22b-instruct' => 'Mistral: Mixtral 8x22B Instruct (Affordable, Functions)',
        'mistralai/mixtral-8x7b-instruct' => 'Mistral: Mixtral 8x7B Instruct (Very cheap, Functions)',
        'mistralai/pixtral-12b' => 'Mistral: Pixtral 12B (Very cheap, Vision, Functions)',
        'mistralai/pixtral-large-2411' => 'Mistral: Pixtral Large 2411 (131k context, Vision, Functions)',

        // X-ai Models
        'x-ai/grok-2-1212' => 'xAI: Grok 2 1212 (131k context, Functions)',
        'x-ai/grok-2-vision-1212' => 'xAI: Grok 2 Vision 1212 (Vision)',
        'x-ai/grok-3' => 'xAI: Grok 3 (131k context, Functions)',
        'x-ai/grok-3-beta' => 'xAI: Grok 3 Beta (131k context, Functions)',
        'x-ai/grok-3-mini' => 'xAI: Grok 3 Mini (131k context, Very cheap, Functions)',
        'x-ai/grok-3-mini-beta' => 'xAI: Grok 3 Mini Beta (131k context, Very cheap, Functions)',
        'x-ai/grok-4' => 'xAI: Grok 4 (256k context, Vision, Functions)',
        'x-ai/grok-code-fast-1' => 'xAI: Grok Code Fast 1 (256k context, Very cheap, Functions)',
        'x-ai/grok-vision-beta' => 'xAI: Grok Vision Beta (Vision)',

        // Deepseek Models
        'deepseek/deepseek-chat' => 'DeepSeek: DeepSeek V3 (164k context, Very cheap, Functions)',
        'deepseek/deepseek-chat-v3-0324' => 'DeepSeek: DeepSeek V3 0324 (164k context, Very cheap, Functions)',
        'deepseek/deepseek-chat-v3-0324:free' => 'DeepSeek: DeepSeek V3 0324 (free) (164k context, Free, Functions)',
        'deepseek/deepseek-chat-v3.1' => 'DeepSeek: DeepSeek V3.1 (164k context, Very cheap, Functions)',
        'deepseek/deepseek-chat-v3.1:free' => 'DeepSeek: DeepSeek V3.1 (free) (Free, Functions)',
        'deepseek/deepseek-prover-v2' => 'DeepSeek: DeepSeek Prover V2 (164k context, Affordable)',
        'deepseek/deepseek-r1' => 'DeepSeek: R1 (164k context, Very cheap, Functions)',
        'deepseek/deepseek-r1-0528' => 'DeepSeek: R1 0528 (164k context, Very cheap, Functions)',
        'deepseek/deepseek-r1-0528-qwen3-8b' => 'DeepSeek: Deepseek R1 0528 Qwen3 8B (Very cheap)',
        'deepseek/deepseek-r1-0528-qwen3-8b:free' => 'DeepSeek: Deepseek R1 0528 Qwen3 8B (free) (131k context, Free)',
        'deepseek/deepseek-r1-0528:free' => 'DeepSeek: R1 0528 (free) (164k context, Free)',
        'deepseek/deepseek-r1-distill-llama-70b' => 'DeepSeek: R1 Distill Llama 70B (131k context, Very cheap, Functions)',
        'deepseek/deepseek-r1-distill-llama-70b:free' => 'DeepSeek: R1 Distill Llama 70B (free) (Free)',
        'deepseek/deepseek-r1-distill-llama-8b' => 'DeepSeek: R1 Distill Llama 8B (Very cheap)',
        'deepseek/deepseek-r1-distill-qwen-14b' => 'DeepSeek: R1 Distill Qwen 14B (Very cheap)',
        'deepseek/deepseek-r1-distill-qwen-14b:free' => 'DeepSeek: R1 Distill Qwen 14B (free) (Free)',
        'deepseek/deepseek-r1-distill-qwen-32b' => 'DeepSeek: R1 Distill Qwen 32B (131k context, Very cheap)',
        'deepseek/deepseek-r1:free' => 'DeepSeek: R1 (free) (164k context, Free)',
        'deepseek/deepseek-v3.1-base' => 'DeepSeek: DeepSeek V3.1 Base (164k context, Very cheap)',

        // Agentica-org Models
        'agentica-org/deepcoder-14b-preview' => 'Agentica: Deepcoder 14B Preview (Very cheap)',
        'agentica-org/deepcoder-14b-preview:free' => 'Agentica: Deepcoder 14B Preview (free) (Free)',

        // Ai21 Models
        'ai21/jamba-large-1.7' => 'AI21: Jamba Large 1.7 (256k context, Functions)',
        'ai21/jamba-mini-1.7' => 'AI21: Jamba Mini 1.7 (256k context, Very cheap, Functions)',

        // Aion-labs Models
        'aion-labs/aion-1.0' => 'AionLabs: Aion-1.0 (131k context)',
        'aion-labs/aion-1.0-mini' => 'AionLabs: Aion-1.0-Mini (131k context, Affordable)',
        'aion-labs/aion-rp-llama-3.1-8b' => 'AionLabs: Aion-RP 1.0 (8B) (Very cheap)',

        // Alfredpros Models
        'alfredpros/codellama-7b-instruct-solidity' => 'AlfredPros: CodeLLaMa 7B Instruct Solidity (Affordable)',

        // Alpindale Models
        'alpindale/goliath-120b' => 'Goliath 120B',

        // Amazon Models
        'amazon/nova-lite-v1' => 'Amazon: Nova Lite 1.0 (300k context, Very cheap, Vision, Functions)',
        'amazon/nova-micro-v1' => 'Amazon: Nova Micro 1.0 (128k context, Very cheap, Functions)',
        'amazon/nova-pro-v1' => 'Amazon: Nova Pro 1.0 (300k context, Affordable, Vision, Functions)',

        // Anthracite-org Models
        'anthracite-org/magnum-v2-72b' => 'Magnum v2 72B',
        'anthracite-org/magnum-v4-72b' => 'Magnum v4 72B',

        // Arcee-ai Models
        'arcee-ai/coder-large' => 'Arcee AI: Coder Large (Affordable)',
        'arcee-ai/maestro-reasoning' => 'Arcee AI: Maestro Reasoning (131k context, Affordable)',
        'arcee-ai/spotlight' => 'Arcee AI: Spotlight (131k context, Very cheap, Vision)',
        'arcee-ai/virtuoso-large' => 'Arcee AI: Virtuoso Large (131k context, Affordable, Functions)',

        // Arliai Models
        'arliai/qwq-32b-arliai-rpr-v1' => 'ArliAI: QwQ 32B RpR v1 (Very cheap)',
        'arliai/qwq-32b-arliai-rpr-v1:free' => 'ArliAI: QwQ 32B RpR v1 (free) (Free)',

        // Baidu Models
        'baidu/ernie-4.5-21b-a3b' => 'Baidu: ERNIE 4.5 21B A3B (Very cheap)',
        'baidu/ernie-4.5-300b-a47b' => 'Baidu: ERNIE 4.5 300B A47B  (Very cheap)',
        'baidu/ernie-4.5-vl-28b-a3b' => 'Baidu: ERNIE 4.5 VL 28B A3B (Very cheap, Vision)',
        'baidu/ernie-4.5-vl-424b-a47b' => 'Baidu: ERNIE 4.5 VL 424B A47B  (Very cheap, Vision)',

        // Bytedance Models
        'bytedance/ui-tars-1.5-7b' => 'Bytedance: UI-TARS 7B  (128k context, Very cheap, Vision)',

        // Cognitivecomputations Models
        'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => 'Venice: Uncensored (free) (Free)',
        'cognitivecomputations/dolphin-mixtral-8x22b' => 'Dolphin 2.9.2 Mixtral 8x22B 🐬 (Affordable)',
        'cognitivecomputations/dolphin3.0-mistral-24b' => 'Dolphin3.0 Mistral 24B (Very cheap)',
        'cognitivecomputations/dolphin3.0-mistral-24b:free' => 'Dolphin3.0 Mistral 24B (free) (Free)',
        'cognitivecomputations/dolphin3.0-r1-mistral-24b' => 'Dolphin3.0 R1 Mistral 24B (Very cheap)',
        'cognitivecomputations/dolphin3.0-r1-mistral-24b:free' => 'Dolphin3.0 R1 Mistral 24B (free) (Free)',

        // Cohere Models
        'cohere/command' => 'Cohere: Command (Affordable)',
        'cohere/command-a' => 'Cohere: Command A',
        'cohere/command-r' => 'Cohere: Command R (128k context, Affordable, Functions)',
        'cohere/command-r-03-2024' => 'Cohere: Command R (03-2024) (128k context, Affordable, Functions)',
        'cohere/command-r-08-2024' => 'Cohere: Command R (08-2024) (128k context, Very cheap, Functions)',
        'cohere/command-r-plus' => 'Cohere: Command R+ (128k context, Functions)',
        'cohere/command-r-plus-04-2024' => 'Cohere: Command R+ (04-2024) (128k context, Functions)',
        'cohere/command-r-plus-08-2024' => 'Cohere: Command R+ (08-2024) (128k context, Functions)',
        'cohere/command-r7b-12-2024' => 'Cohere: Command R7B (12-2024) (128k context, Very cheap)',

        // Eleutherai Models
        'eleutherai/llemma_7b' => 'EleutherAI: Llemma 7b (Affordable)',

        // Gryphe Models
        'gryphe/mythomax-l2-13b' => 'MythoMax 13B (Very cheap)',

        // Inception Models
        'inception/mercury' => 'Inception: Mercury (128k context, Very cheap, Functions)',
        'inception/mercury-coder' => 'Inception: Mercury Coder (128k context, Very cheap, Functions)',

        // Infermatic Models
        'infermatic/mn-inferor-12b' => 'Infermatic: Mistral Nemo Inferor 12B (Affordable)',

        // Inflection Models
        'inflection/inflection-3-pi' => 'Inflection: Inflection 3 Pi',
        'inflection/inflection-3-productivity' => 'Inflection: Inflection 3 Productivity',

        // Liquid Models
        'liquid/lfm-3b' => 'Liquid: LFM 3B (Very cheap)',
        'liquid/lfm-7b' => 'Liquid: LFM 7B (Very cheap)',

        // Mancer Models
        'mancer/weaver' => 'Mancer: Weaver (alpha) (Affordable)',

        // Microsoft Models
        'microsoft/mai-ds-r1' => 'Microsoft: MAI DS R1 (164k context, Very cheap)',
        'microsoft/mai-ds-r1:free' => 'Microsoft: MAI DS R1 (free) (164k context, Free)',
        'microsoft/phi-3-medium-128k-instruct' => 'Microsoft: Phi-3 Medium 128K Instruct (128k context, Affordable, Functions)',
        'microsoft/phi-3-mini-128k-instruct' => 'Microsoft: Phi-3 Mini 128K Instruct (128k context, Very cheap, Functions)',
        'microsoft/phi-3.5-mini-128k-instruct' => 'Microsoft: Phi-3.5 Mini 128K Instruct (128k context, Very cheap, Functions)',
        'microsoft/phi-4' => 'Microsoft: Phi 4 (Very cheap)',
        'microsoft/phi-4-multimodal-instruct' => 'Microsoft: Phi 4 Multimodal Instruct (131k context, Very cheap, Vision)',
        'microsoft/phi-4-reasoning-plus' => 'Microsoft: Phi 4 Reasoning Plus (Very cheap)',
        'microsoft/wizardlm-2-8x22b' => 'WizardLM-2 8x22B (Very cheap)',

        // Minimax Models
        'minimax/minimax-01' => 'MiniMax: MiniMax-01 (1000k context, Very cheap, Vision)',
        'minimax/minimax-m1' => 'MiniMax: MiniMax M1 (1000k context, Very cheap, Functions)',

        // Moonshotai Models
        'moonshotai/kimi-dev-72b' => 'MoonshotAI: Kimi Dev 72B (131k context, Very cheap)',
        'moonshotai/kimi-dev-72b:free' => 'MoonshotAI: Kimi Dev 72B (free) (131k context, Free)',
        'moonshotai/kimi-k2' => 'MoonshotAI: Kimi K2 (Very cheap, Functions)',
        'moonshotai/kimi-k2:free' => 'MoonshotAI: Kimi K2 (free) (Free, Functions)',
        'moonshotai/kimi-vl-a3b-thinking' => 'MoonshotAI: Kimi VL A3B Thinking (131k context, Very cheap, Vision)',
        'moonshotai/kimi-vl-a3b-thinking:free' => 'MoonshotAI: Kimi VL A3B Thinking (free) (131k context, Free, Vision)',

        // Morph Models
        'morph/morph-v3-fast' => 'Morph: Morph V3 Fast (Affordable)',
        'morph/morph-v3-large' => 'Morph: Morph V3 Large (Affordable)',

        // Neversleep Models
        'neversleep/llama-3-lumimaid-70b' => 'NeverSleep: Llama 3 Lumimaid 70B',
        'neversleep/llama-3.1-lumimaid-8b' => 'NeverSleep: Lumimaid v0.2 8B (Very cheap)',
        'neversleep/noromaid-20b' => 'Noromaid 20B (Affordable)',

        // Nousresearch Models
        'nousresearch/deephermes-3-llama-3-8b-preview:free' => 'Nous: DeepHermes 3 Llama 3 8B Preview (free) (131k context, Free)',
        'nousresearch/deephermes-3-mistral-24b-preview' => 'Nous: DeepHermes 3 Mistral 24B Preview (Very cheap)',
        'nousresearch/hermes-2-pro-llama-3-8b' => 'NousResearch: Hermes 2 Pro - Llama-3 8B (131k context, Very cheap)',
        'nousresearch/hermes-3-llama-3.1-405b' => 'Nous: Hermes 3 405B Instruct (131k context, Affordable)',
        'nousresearch/hermes-3-llama-3.1-70b' => 'Nous: Hermes 3 70B Instruct (131k context, Very cheap, Functions)',
        'nousresearch/hermes-4-405b' => 'Nous: Hermes 4 405B (131k context, Very cheap, Functions)',
        'nousresearch/hermes-4-70b' => 'Nous: Hermes 4 70B (131k context, Very cheap, Functions)',

        // Nvidia Models
        'nvidia/llama-3.1-nemotron-70b-instruct' => 'NVIDIA: Llama 3.1 Nemotron 70B Instruct (131k context, Very cheap, Functions)',
        'nvidia/llama-3.1-nemotron-ultra-253b-v1' => 'NVIDIA: Llama 3.1 Nemotron Ultra 253B v1 (131k context, Affordable)',
        'nvidia/llama-3.1-nemotron-ultra-253b-v1:free' => 'NVIDIA: Llama 3.1 Nemotron Ultra 253B v1 (free) (131k context, Free)',
        'nvidia/llama-3.3-nemotron-super-49b-v1' => 'NVIDIA: Llama 3.3 Nemotron Super 49B v1 (131k context, Very cheap)',

        // Opengvlab Models
        'opengvlab/internvl3-14b' => 'OpenGVLab: InternVL3 14B (Very cheap, Vision)',

        // Openrouter Models
        'openrouter/auto' => 'Auto Router (2000k context, Very cheap)',

        // Perplexity Models
        'perplexity/r1-1776' => 'Perplexity: R1 1776 (128k context)',
        'perplexity/sonar' => 'Perplexity: Sonar (Affordable, Vision)',
        'perplexity/sonar-deep-research' => 'Perplexity: Sonar Deep Research (128k context)',
        'perplexity/sonar-pro' => 'Perplexity: Sonar Pro (200k context, Vision)',
        'perplexity/sonar-reasoning' => 'Perplexity: Sonar Reasoning (Affordable)',
        'perplexity/sonar-reasoning-pro' => 'Perplexity: Sonar Reasoning Pro (128k context, Vision)',

        // Pygmalionai Models
        'pygmalionai/mythalion-13b' => 'Pygmalion: Mythalion 13B (Affordable)',

        // Qwen Models
        'qwen/qwen-2.5-72b-instruct' => 'Qwen2.5 72B Instruct (Very cheap, Functions)',
        'qwen/qwen-2.5-72b-instruct:free' => 'Qwen2.5 72B Instruct (free) (Free)',
        'qwen/qwen-2.5-7b-instruct' => 'Qwen2.5 7B Instruct (Very cheap)',
        'qwen/qwen-2.5-coder-32b-instruct' => 'Qwen2.5 Coder 32B Instruct (Very cheap)',
        'qwen/qwen-2.5-coder-32b-instruct:free' => 'Qwen2.5 Coder 32B Instruct (free) (Free)',
        'qwen/qwen-2.5-vl-7b-instruct' => 'Qwen: Qwen2.5-VL 7B Instruct (Very cheap, Vision)',
        'qwen/qwen-max' => 'Qwen: Qwen-Max  (Affordable, Functions)',
        'qwen/qwen-plus' => 'Qwen: Qwen-Plus (131k context, Very cheap, Functions)',
        'qwen/qwen-turbo' => 'Qwen: Qwen-Turbo (1000k context, Very cheap, Functions)',
        'qwen/qwen-vl-max' => 'Qwen: Qwen VL Max (Affordable, Vision)',
        'qwen/qwen-vl-plus' => 'Qwen: Qwen VL Plus (Very cheap, Vision)',
        'qwen/qwen2.5-vl-32b-instruct' => 'Qwen: Qwen2.5 VL 32B Instruct (Very cheap, Vision)',
        'qwen/qwen2.5-vl-32b-instruct:free' => 'Qwen: Qwen2.5 VL 32B Instruct (free) (Free, Vision)',
        'qwen/qwen2.5-vl-72b-instruct' => 'Qwen: Qwen2.5 VL 72B Instruct (Very cheap, Vision)',
        'qwen/qwen2.5-vl-72b-instruct:free' => 'Qwen: Qwen2.5 VL 72B Instruct (free) (Free, Vision)',
        'qwen/qwen3-14b' => 'Qwen: Qwen3 14B (Very cheap, Functions)',
        'qwen/qwen3-14b:free' => 'Qwen: Qwen3 14B (free) (Free)',
        'qwen/qwen3-235b-a22b' => 'Qwen: Qwen3 235B A22B (Very cheap, Functions)',
        'qwen/qwen3-235b-a22b-2507' => 'Qwen: Qwen3 235B A22B Instruct 2507 (262k context, Very cheap, Functions)',
        'qwen/qwen3-235b-a22b-thinking-2507' => 'Qwen: Qwen3 235B A22B Thinking 2507 (262k context, Very cheap, Functions)',
        'qwen/qwen3-235b-a22b:free' => 'Qwen: Qwen3 235B A22B (free) (131k context, Free, Functions)',
        'qwen/qwen3-30b-a3b' => 'Qwen: Qwen3 30B A3B (Very cheap, Functions)',
        'qwen/qwen3-30b-a3b-instruct-2507' => 'Qwen: Qwen3 30B A3B Instruct 2507 (262k context, Very cheap, Functions)',
        'qwen/qwen3-30b-a3b-thinking-2507' => 'Qwen: Qwen3 30B A3B Thinking 2507 (262k context, Very cheap, Functions)',
        'qwen/qwen3-30b-a3b:free' => 'Qwen: Qwen3 30B A3B (free) (Free)',
        'qwen/qwen3-32b' => 'Qwen: Qwen3 32B (Very cheap, Functions)',
        'qwen/qwen3-4b:free' => 'Qwen: Qwen3 4B (free) (Free, Functions)',
        'qwen/qwen3-8b' => 'Qwen: Qwen3 8B (128k context, Very cheap)',
        'qwen/qwen3-8b:free' => 'Qwen: Qwen3 8B (free) (Free)',
        'qwen/qwen3-coder' => 'Qwen: Qwen3 Coder 480B A35B (262k context, Very cheap, Functions)',
        'qwen/qwen3-coder-30b-a3b-instruct' => 'Qwen: Qwen3 Coder 30B A3B Instruct (262k context, Very cheap, Functions)',
        'qwen/qwen3-coder:free' => 'Qwen: Qwen3 Coder 480B A35B (free) (262k context, Free, Functions)',
        'qwen/qwq-32b' => 'Qwen: QwQ 32B (131k context, Very cheap, Functions)',
        'qwen/qwq-32b-preview' => 'Qwen: QwQ 32B Preview (Very cheap)',
        'qwen/qwq-32b:free' => 'Qwen: QwQ 32B (free) (Free)',

        // Raifle Models
        'raifle/sorcererlm-8x22b' => 'SorcererLM 8x22B',

        // Rekaai Models
        'rekaai/reka-flash-3:free' => 'Reka: Flash 3 (free) (Free)',

        // Sao10k Models
        'sao10k/l3-euryale-70b' => 'Sao10k: Llama 3 Euryale 70B v2.1 (Affordable)',
        'sao10k/l3-lunaris-8b' => 'Sao10K: Llama 3 8B Lunaris (Very cheap)',
        'sao10k/l3.1-euryale-70b' => 'Sao10K: Llama 3.1 Euryale 70B v2.2 (Affordable)',
        'sao10k/l3.3-euryale-70b' => 'Sao10K: Llama 3.3 Euryale 70B (131k context, Affordable)',

        // Sarvamai Models
        'sarvamai/sarvam-m:free' => 'Sarvam AI: Sarvam-M (free) (Free)',

        // Scb10x Models
        'scb10x/llama3.1-typhoon2-70b-instruct' => 'Typhoon2 70B Instruct (Affordable)',

        // Shisa-ai Models
        'shisa-ai/shisa-v2-llama3.3-70b' => 'Shisa AI: Shisa V2 Llama 3.3 70B  (Very cheap)',
        'shisa-ai/shisa-v2-llama3.3-70b:free' => 'Shisa AI: Shisa V2 Llama 3.3 70B  (free) (Free)',

        // Sophosympatheia Models
        'sophosympatheia/midnight-rose-70b' => 'Midnight Rose 70B (Affordable)',

        // Switchpoint Models
        'switchpoint/router' => 'Switchpoint Router (131k context, Affordable)',

        // Tencent Models
        'tencent/hunyuan-a13b-instruct' => 'Tencent: Hunyuan A13B Instruct (Very cheap)',
        'tencent/hunyuan-a13b-instruct:free' => 'Tencent: Hunyuan A13B Instruct (free) (Free)',

        // Thedrummer Models
        'thedrummer/anubis-70b-v1.1' => 'TheDrummer: Anubis 70B V1.1 (Very cheap)',
        'thedrummer/anubis-pro-105b-v1' => 'TheDrummer: Anubis Pro 105B V1 (131k context, Affordable)',
        'thedrummer/rocinante-12b' => 'TheDrummer: Rocinante 12B (Very cheap, Functions)',
        'thedrummer/skyfall-36b-v2' => 'TheDrummer: Skyfall 36B V2 (Very cheap)',
        'thedrummer/unslopnemo-12b' => 'TheDrummer: UnslopNemo 12B (Very cheap, Functions)',

        // Thudm Models
        'thudm/glm-4-32b' => 'THUDM: GLM 4 32B (Affordable)',
        'thudm/glm-4.1v-9b-thinking' => 'THUDM: GLM 4.1V 9B Thinking (Very cheap, Vision)',
        'thudm/glm-z1-32b' => 'THUDM: GLM Z1 32B (Very cheap)',

        // Tngtech Models
        'tngtech/deepseek-r1t-chimera' => 'TNG: DeepSeek R1T Chimera (164k context, Very cheap)',
        'tngtech/deepseek-r1t-chimera:free' => 'TNG: DeepSeek R1T Chimera (free) (164k context, Free)',
        'tngtech/deepseek-r1t2-chimera:free' => 'TNG: DeepSeek R1T2 Chimera (free) (164k context, Free)',

        // Undi95 Models
        'undi95/remm-slerp-l2-13b' => 'ReMM SLERP 13B (Very cheap)',

        // Z-ai Models
        'z-ai/glm-4-32b' => 'Z.AI: GLM 4 32B  (128k context, Very cheap, Functions)',
        'z-ai/glm-4.5' => 'Z.AI: GLM 4.5 (131k context, Very cheap, Functions)',
        'z-ai/glm-4.5-air' => 'Z.AI: GLM 4.5 Air (131k context, Very cheap, Functions)',
        'z-ai/glm-4.5-air:free' => 'Z.AI: GLM 4.5 Air (free) (131k context, Free, Functions)',
        'z-ai/glm-4.5v' => 'Z.AI: GLM 4.5V (Affordable, Vision, Functions)',

    ],
];
