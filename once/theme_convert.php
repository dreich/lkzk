<?php

// Check if the file path is provided
if (!isset($argv[1])) {
    die("Usage: php theme_convert.php path/to/theme.tmTheme\n");
}

$themeFile = $argv[1];

// Check if the file exists and is readable
if (!file_exists($themeFile) || !is_readable($themeFile)) {
    die("Error: Cannot read theme file: $themeFile\n");
}

// Load and parse the XML file
$xml = simplexml_load_file($themeFile);
if ($xml === false) {
    die("Error: Failed to parse XML file\n");
}

// Initialize the theme array
$windsurfTheme = [
    'settings' => [
        'rules' => []
    ]
];



// Check if $xml is defined and has the expected structure
if (!isset($xml) || !isset($xml->dict) || !isset($xml->dict->array) || !isset($xml->dict->array->dict)) {
    die("Error: Invalid XML structure. Expected 'dict/array/dict' elements.");
}

// Initialize the theme array
$windsurfTheme = [
    'settings' => [
        'rules' => []
    ]
];

// Process style rules
foreach ($xml->dict->array->dict as $index => $dict) {
    if ($index == 0) continue; // Skip the first dict with global settings
    
    $rule = [];
    $name = '';
    $scope = '';
    $settings = [];
    
    // Get all child elements
    $children = $dict->children();
    $childCount = count($children);
    
    for ($i = 0; $i < $childCount; $i++) {
        $child = $children[$i];
        if ($child->getName() == 'key') {
            $keyName = (string)$child;
            $valueNode = isset($children[++$i]) ? $children[$i] : null;
            
            if ($valueNode) {
                if ($keyName == 'name') {
                    $name = (string)$valueNode;
                } 
                elseif ($keyName == 'scope') {
                    $scope = (string)$valueNode;
                }
                elseif ($keyName == 'settings' && $valueNode->getName() == 'dict') {
                    foreach ($valueNode->children() as $setting) {
                        if ($setting->getName() == 'key') {
                            $settingName = (string)$setting;
                            $settingValue = $setting->xpath('following-sibling::*[1]');
                            if (!empty($settingValue)) {
                                $settings[$settingName] = (string)$settingValue[0];
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Add the rule if there's at least a name or settings
    if (!empty($name) || !empty($settings)) {
        $ruleData = [];
        if (!empty($name)) $ruleData['name'] = $name;
        if (!empty($scope)) $ruleData['scope'] = $scope;
        if (!empty($settings)) $ruleData['settings'] = $settings;
        
        $windsurfTheme['settings']['rules'][] = $ruleData;
    }
}

// For debugging, output the number of found rules
$ruleCount = count($windsurfTheme['settings']['rules']);
echo "Found rules: $ruleCount\n";

// You can also output the result as JSON
// echo json_encode($windsurfTheme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);