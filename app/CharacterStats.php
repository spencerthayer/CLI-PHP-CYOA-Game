<?php

namespace App;

class CharacterStats
{
    // Base Attributes with consistent structure
    private $attributes = [
        // Primary Attributes
        'Agility'    => ['current' => null, 'max' => 20],
        'Appearance' => ['current' => null, 'max' => 20],
        'Charisma'   => ['current' => null, 'max' => 20],
        'Dexterity'  => ['current' => null, 'max' => 20],
        'Endurance'  => ['current' => null, 'max' => 20],
        'Intellect'  => ['current' => null, 'max' => 20],
        'Knowledge'  => ['current' => null, 'max' => 20],
        'Luck'       => ['current' => null, 'max' => 20],
        'Perception' => ['current' => null, 'max' => 20],
        'Spirit'     => ['current' => null, 'max' => 20],
        'Strength'   => ['current' => null, 'max' => 20],
        'Vitality'   => ['current' => null, 'max' => 20],
        'Willpower'  => ['current' => null, 'max' => 20],
        'Wisdom'     => ['current' => null, 'max' => 20],
        
        // Derived Stats
        'Health'  => ['current' => 0, 'max' => 0],
        'Focus'   => ['current' => 0, 'max' => 0],
        'Stamina' => ['current' => 0, 'max' => 0],
        'Courage' => ['current' => 0, 'max' => 0],
        'Sanity'  => ['current' => 0, 'max' => 0]
    ];

    private $level = 1;
    private $experience = 0;
    private $debug = false;

    // Base values for derived stats
    private $baseValues = [
        'Health'  => 20,  // Base Health
        'Focus'   => 10,  // Base Focus Points
        'Stamina' => 10,  // Base Stamina
        'Courage' => 10,  // Base Courage
        'Sanity'  => 20   // Base Sanity
    ];

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->initializeStats();

        if ($this->debug) {
            write_debug_log("CharacterStats initialized", [
                'stats' => $this->getStats()
            ]);
        }
    }

    private function initializeStats()
    {
        // Initialize Primary Attributes with random values between 8 and 18
        foreach ($this->attributes as $attribute => $values) {
            if (!in_array($attribute, ['Health', 'Focus', 'Stamina', 'Courage', 'Sanity'])) {
                $this->attributes[$attribute]['current'] = random_int(8, 18);
            }
        }
        
        // Calculate Health = (Vitality × 2) + Endurance
        $vitality = $this->attributes['Vitality']['current'];
        $endurance = $this->attributes['Endurance']['current'];
        $this->attributes['Health']['max'] = floor(($vitality * 2) + $endurance);
        $this->attributes['Health']['current'] = $this->attributes['Health']['max'];
        
        // Calculate Focus = Willpower + (Intellect + Wisdom)/2
        $willpower = $this->attributes['Willpower']['current'];
        $intellect = $this->attributes['Intellect']['current'];
        $wisdom = $this->attributes['Wisdom']['current'];
        $this->attributes['Focus']['max'] = floor($willpower + (($intellect + $wisdom) / 2));
        $this->attributes['Focus']['current'] = $this->attributes['Focus']['max'];
        
        // Calculate Stamina = (Endurance × 1.5) + (Strength + Agility)/2
        $strength = $this->attributes['Strength']['current'];
        $agility = $this->attributes['Agility']['current'];
        $this->attributes['Stamina']['max'] = floor(($endurance * 1.5) + (($strength + $agility) / 2));
        $this->attributes['Stamina']['current'] = $this->attributes['Stamina']['max'];

        // Calculate Courage = Willpower + (Spirit + Charisma)/2
        $spirit = $this->attributes['Spirit']['current'];
        $charisma = $this->attributes['Charisma']['current'];
        $this->attributes['Courage']['max'] = floor($willpower + (($spirit + $charisma) / 2));
        $this->attributes['Courage']['current'] = $this->attributes['Courage']['max'];

        // Calculate Sanity = (Willpower × 1.5) + (Intellect + Perception)/2
        $perception = $this->attributes['Perception']['current'];
        $this->attributes['Sanity']['max'] = floor(($willpower * 1.5) + (($intellect + $perception) / 2));
        $this->attributes['Sanity']['current'] = $this->attributes['Sanity']['max'];

        if ($this->debug) {
            write_debug_log("Stats Initialized", [
                'derived_stats' => [
                    'Health' => $this->attributes['Health'],
                    'Focus' => $this->attributes['Focus'],
                    'Stamina' => $this->attributes['Stamina'],
                    'Courage' => $this->attributes['Courage'],
                    'Sanity' => $this->attributes['Sanity']
                ]
            ]);
        }
    }

    public function modifyStat($stat, $amount)
    {
        if (!isset($this->attributes[$stat])) {
            throw new \Exception("Invalid stat: $stat");
        }

        $current = $this->attributes[$stat]['current'];
        $max = $this->attributes[$stat]['max'];
        $new_value = max(0, min($max, $current + $amount));
        $this->attributes[$stat]['current'] = $new_value;
        
        if ($this->debug) {
            write_debug_log("Modified $stat", [
                'old_value' => $current,
                'change' => $amount,
                'new_value' => $new_value,
                'max' => $max
            ]);
        }
        
        return $new_value;
    }

    public function setStatMax($stat, $value)
    {
        if (!isset($this->attributes[$stat])) {
            throw new \Exception("Invalid stat: $stat");
        }

        $old_max = $this->attributes[$stat]['max'];
        $this->attributes[$stat]['max'] = max(0, $value);
        
        // Adjust current value if it exceeds new max
        if ($this->attributes[$stat]['current'] > $this->attributes[$stat]['max']) {
            $this->attributes[$stat]['current'] = $this->attributes[$stat]['max'];
        }
        
        if ($this->debug) {
            write_debug_log("Modified $stat Max", [
                'old_max' => $old_max,
                'new_max' => $this->attributes[$stat]['max'],
                'current' => $this->attributes[$stat]['current']
            ]);
        }
    }

    public function skillCheck($attribute, $difficulty = 15, $proficiencyBonus = 0)
    {
        if (!isset($this->attributes[$attribute])) {
            throw new \Exception("Invalid attribute: $attribute");
        }

        $attribute_value = $this->attributes[$attribute]['current'];
        
        if ($this->debug) {
            write_debug_log("Skill Check Debug", [
                'attribute_name' => $attribute,
                'attribute_raw' => $this->attributes[$attribute],
                'attribute_value' => $attribute_value,
                'difficulty' => $difficulty
            ]);
        }
        
        $roll = rand(1, 20);
        $modifier = $this->calculateModifier($attribute_value);
        
        if ($this->debug) {
            write_debug_log("Modifier Calculation", [
                'attribute_value' => $attribute_value,
                'calculation' => "floor(($attribute_value - 10) / 2)",
                'modifier_result' => $modifier
            ]);
        }
        
        $total = $roll + $modifier + $proficiencyBonus;
        $success = $total >= $difficulty;

        $result = [
            'success' => $success,
            'roll' => $roll,
            'modifier' => $modifier,
            'proficiency' => $proficiencyBonus,
            'total' => $total,
            'difficulty' => $difficulty,
            'attribute_score' => $attribute_value,
            'details' => sprintf(
                "Rolled %d + %d (modifier) + %d (proficiency) = %d vs DC %d",
                $roll, $modifier, $proficiencyBonus, $total, $difficulty
            )
        ];

        if ($attribute === 'Sanity' && !$success) {
            $sanity_loss = rand(2, 4);
            $this->modifyStat('Sanity', -$sanity_loss);
        }

        if ($this->debug) {
            write_debug_log("🎲 Skill Check Result", [
                'type' => 'skill_check',
                'attribute' => $attribute,
                'base_score' => $attribute_value,
                'roll' => [
                    'die' => 'd20',
                    'result' => $roll,
                    'modifier' => $modifier,
                    'proficiency' => $proficiencyBonus,
                    'total' => $total
                ],
                'difficulty' => $difficulty,
                'success' => $success,
                'narrative_impact' => $success ? 
                    "Character successfully uses their {$attribute} (DC {$difficulty})" :
                    "Character fails to use their {$attribute} effectively (DC {$difficulty})"
            ]);
        }

        return $result;
    }

    public function savingThrow($type, $difficulty = 15)
    {
        $attribute = '';
        switch ($type) {
            case 'Fortitude':
                $attribute = 'Vitality';
                break;
            case 'Reflex':
                $attribute = 'Dexterity';
                break;
            case 'Will':
                $attribute = 'Willpower';
                break;
            case 'Social':
                $attribute = 'Charisma';
                break;
            default:
                throw new \Exception("Invalid saving throw type: $type");
        }

        $attribute_value = $this->attributes[$attribute]['current'];
        $roll = rand(1, 20);
        $modifier = $this->calculateModifier($attribute_value);
        $total = $roll + $modifier;
        $success = $total >= $difficulty;

        $result = [
            'success' => $success,
            'roll' => $roll,
            'modifier' => $modifier,
            'total' => $total,
            'difficulty' => $difficulty,
            'save_type' => $type,
            'attribute' => $attribute,
            'attribute_score' => $attribute_value,
            'details' => sprintf(
                "Rolled %d + %d (modifier) = %d vs DC %d",
                $roll, $modifier, $total, $difficulty
            )
        ];

        if ($this->debug) {
            write_debug_log("🎲 Saving Throw Result", [
                'type' => 'saving_throw',
                'save_type' => $type,
                'attribute' => $attribute,
                'base_score' => $attribute_value,
                'roll' => [
                    'die' => 'd20',
                    'result' => $roll,
                    'modifier' => $modifier,
                    'total' => $total
                ],
                'difficulty' => $difficulty,
                'success' => $success,
                'narrative_impact' => $success ? 
                    "Character successfully resists the {$type} effect (DC {$difficulty})" :
                    "Character fails to resist the {$type} effect (DC {$difficulty})"
            ]);
        }

        return $result;
    }

    public function sanityCheck($difficulty = 15)
    {
        $result = $this->skillCheck('Sanity', $difficulty);
        
        if (!$result['success']) {
            $sanity_loss = rand(2, 4);
            $old_sanity = $this->attributes['Sanity']['current'];
            $this->modifyStat('Sanity', -$sanity_loss);
            
            if ($this->debug) {
                write_debug_log("Failed Sanity Check", [
                    'old_sanity' => $old_sanity,
                    'sanity_loss' => $sanity_loss,
                    'new_sanity' => $this->attributes['Sanity']['current']
                ]);
            }
            
            $result['sanityLoss'] = $sanity_loss;
            $result['currentSanity'] = $this->attributes['Sanity']['current'];
        }
        
        return $result;
    }

    public function takeDamage($amount)
    {
        $old_health = $this->attributes['Health']['current'];
        $this->modifyStat('Health', -$amount);
        $new_health = $this->attributes['Health']['current'];

        if ($this->debug) {
            write_debug_log("Taking Damage", [
                'damage_amount' => $amount,
                'old_health' => $old_health,
                'new_health' => $new_health
            ]);
        }

        return $new_health;
    }

    public function heal($amount)
    {
        $old_health = $this->attributes['Health']['current'];
        $this->modifyStat('Health', $amount);
        $new_health = $this->attributes['Health']['current'];
        
        if ($this->debug) {
            write_debug_log("Healing applied", [
                'old_health' => $old_health,
                'amount_healed' => $amount,
                'new_health' => $new_health,
                'max_health' => $this->attributes['Health']['max']
            ]);
        }
        
        return $new_health - $old_health; // Return actual amount healed
    }

    public function useFocus($amount)
    {
        if ($this->attributes['Focus']['current'] >= $amount) {
            $this->modifyStat('Focus', -$amount);
            return true;
        }
        return false;
    }

    public function useStamina($amount)
    {
        if ($this->attributes['Stamina']['current'] >= $amount) {
            $this->modifyStat('Stamina', -$amount);
            return true;
        }
        return false;
    }

    public function rest()
    {
        $stats_to_restore = ['Health', 'Focus', 'Stamina'];
        foreach ($stats_to_restore as $stat) {
            $this->modifyStat($stat, $this->attributes[$stat]['max']);
        }
        
        if ($this->debug) {
            write_debug_log("Rest completed", [
                'restored_stats' => array_map(function($stat) {
                    return [
                        'stat' => $stat,
                        'current' => $this->attributes[$stat]['current'],
                        'max' => $this->attributes[$stat]['max']
                    ];
                }, $stats_to_restore)
            ]);
        }
    }

    public function calculateAC($armorBonus = 0)
    {
        return 10 + $this->calculateModifier($this->attributes['Dexterity']['current']) + $armorBonus;
    }

    public function calculateModifier($score)
    {
        $modifier = floor(($score - 10) / 2);
        if ($this->debug) {
            write_debug_log("Calculating modifier", [
                'score' => $score,
                'calculation' => "floor(($score - 10) / 2)",
                'result' => $modifier
            ]);
        }
        return $modifier;
    }

    public function recalculateStats()
    {
        // Store current percentages of resources
        $percentages = [];
        foreach (['Health', 'Focus', 'Stamina', 'Courage', 'Sanity'] as $stat) {
            $percentages[$stat] = $this->attributes[$stat]['current'] / $this->attributes[$stat]['max'];
        }

        // Recalculate max values
        $vitality = $this->attributes['Vitality']['current'];
        $endurance = $this->attributes['Endurance']['current'];
        $willpower = $this->attributes['Willpower']['current'];
        $intellect = $this->attributes['Intellect']['current'];
        $wisdom = $this->attributes['Wisdom']['current'];
        $strength = $this->attributes['Strength']['current'];
        $agility = $this->attributes['Agility']['current'];
        $spirit = $this->attributes['Spirit']['current'];
        $charisma = $this->attributes['Charisma']['current'];
        $perception = $this->attributes['Perception']['current'];

        $this->attributes['Health']['max'] = floor(($vitality * 2) + $endurance);
        $this->attributes['Focus']['max'] = floor($willpower + (($intellect + $wisdom) / 2));
        $this->attributes['Stamina']['max'] = floor(($endurance * 1.5) + (($strength + $agility) / 2));
        $this->attributes['Courage']['max'] = floor($willpower + (($spirit + $charisma) / 2));
        $this->attributes['Sanity']['max'] = floor(($willpower * 1.5) + (($intellect + $perception) / 2));

        // Restore current values maintaining percentages
        foreach (['Health', 'Focus', 'Stamina', 'Courage', 'Sanity'] as $stat) {
            $this->attributes[$stat]['current'] = round($this->attributes[$stat]['max'] * $percentages[$stat]);
        }

        if ($this->debug) {
            write_debug_log("Stats Recalculated", [
                'new_values' => [
                    'Health' => $this->attributes['Health'],
                    'Focus' => $this->attributes['Focus'],
                    'Stamina' => $this->attributes['Stamina'],
                    'Courage' => $this->attributes['Courage'],
                    'Sanity' => $this->attributes['Sanity']
                ]
            ]);
        }
    }

    public function gainExperience($amount)
    {
        $oldLevel = $this->level;
        $this->experience += $amount;
        
        // Simple leveling formula: level = floor(experience / 1000) + 1
        $newLevel = floor($this->experience / 1000) + 1;
        
        if ($newLevel > $oldLevel) {
            $this->level = $newLevel;
            $this->onLevelUp();
        }
    }

    private function onLevelUp()
    {
        // Increase primary attributes
        $primaryAttributes = ['Agility', 'Appearance', 'Charisma', 'Dexterity', 'Endurance', 'Intellect', 'Knowledge', 'Luck', 'Perception', 'Spirit', 'Strength', 'Vitality', 'Willpower', 'Wisdom'];
        foreach ($primaryAttributes as $attr) {
            if (rand(1, 100) <= 30) { // 30% chance to increase each attribute
                $this->attributes[$attr]['max'] += 1;
                $this->attributes[$attr]['current'] = $this->attributes[$attr]['max'];
            }
        }

        // Recalculate derived stats with new attribute values
        $this->recalculateStats();

        // Fully restore all resources on level up
        foreach (['Health', 'Focus', 'Stamina', 'Courage', 'Sanity'] as $stat) {
            $this->attributes[$stat]['current'] = $this->attributes[$stat]['max'];
        }

        if ($this->debug) {
            write_debug_log("Level Up!", [
                'new_level' => $this->level,
                'primary_attributes' => array_intersect_key($this->attributes, array_flip($primaryAttributes)),
                'derived_stats' => [
                    'Health' => $this->attributes['Health'],
                    'Focus' => $this->attributes['Focus'],
                    'Stamina' => $this->attributes['Stamina'],
                    'Courage' => $this->attributes['Courage'],
                    'Sanity' => $this->attributes['Sanity']
                ]
            ]);
        }
    }

    public function getStats()
    {
        return [
            'attributes' => $this->attributes,
            'level' => $this->level,
            'experience' => $this->experience
        ];
    }

    public function setState($state)
    {
        if (isset($state['attributes'])) {
            $this->attributes = $state['attributes'];
        }
        if (isset($state['level'])) {
            $this->level = $state['level'];
        }
        if (isset($state['experience'])) {
            $this->experience = $state['experience'];
        }
        
        if ($this->debug) {
            write_debug_log("State Loaded", $this->getStats());
        }
    }

    // Getters
    public function getLevel() { return $this->level; }
    public function getExperience() { return $this->experience; }
    
    public function getStat($stat) 
    {
        if (!isset($this->attributes[$stat])) {
            throw new \Exception("Invalid stat: $stat");
        }
        return $this->attributes[$stat];
    }
}
