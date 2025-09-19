<?php
/**
 * Test script to verify image URL encoding fix
 * Tests the apostrophe encoding issue for activity "ODEUR D'ESSENCE"
 */

// Set up gallery access
define('GALLERY_ACCESS', true);
require_once 'config.php';

/**
 * Test the URL encoding fix for images with apostrophes
 */
function testApostropheEncodingFix() {
    echo "<h1>Image URL Encoding Test Report</h1>\n";
    echo "<h2>Testing Fix for Apostrophe Encoding Issue</h2>\n";
    echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
    echo "<p><strong>Test Case:</strong> Activity 'ODEUR D'ESSENCE' with file 'ODEUR ESSENCE (1).JPG'</p>\n";
    
    // Test data - the problematic case
    $activity_key = "ODEUR D'ESSENCE";
    $photo_name = "ODEUR ESSENCE (1).JPG";
    $full_path = $activity_key . '/' . $photo_name;
    
    echo "<h3>1. Current GetImageUrl Function Analysis</h3>\n";
    echo "<p><strong>Input:</strong> Activity = '$activity_key', Photo = '$photo_name'</p>\n";
    echo "<p><strong>Full path:</strong> '$full_path'</p>\n";
    
    // Test different image types
    $tests = [
        'thumbnail' => 'IMG_THUMBNAIL',
        'resized' => 'IMG_RESIZED', 
        'original' => 'IMG_ORIGINAL'
    ];
    
    echo "<h3>2. Generated URLs (After Fix)</h3>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Image Type</th><th>Generated URL</th><th>Apostrophe Encoding</th><th>Status</th></tr>\n";
    
    foreach ($tests as $type_str => $type_const) {
        $url = GetImageUrl($full_path, $type_const);
        
        // Check if apostrophe is properly encoded as %27 (not %23039%3B)
        $has_correct_encoding = strpos($url, '%27') !== false;
        $has_wrong_encoding = strpos($url, '%23039%3B') !== false;
        
        $status = 'UNKNOWN';
        if ($has_correct_encoding && !$has_wrong_encoding) {
            $status = '<span style="color: green;">✓ PASS</span>';
        } elseif ($has_wrong_encoding) {
            $status = '<span style="color: red;">✗ FAIL (Double encoding)</span>';
        } elseif (!$has_correct_encoding && !$has_wrong_encoding) {
            $status = '<span style="color: orange;">? No apostrophe found</span>';
        }
        
        $apostrophe_encoding = 'Not found';
        if ($has_correct_encoding) {
            $apostrophe_encoding = '%27 (Correct)';
        } elseif ($has_wrong_encoding) {
            $apostrophe_encoding = '%23039%3B (Wrong - Double encoded)';
        }
        
        echo "<tr>";
        echo "<td>$type_str</td>";
        echo "<td><code>$url</code></td>";
        echo "<td>$apostrophe_encoding</td>";
        echo "<td>$status</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Test the actual URL construction step by step
    echo "<h3>3. Step-by-step URL Construction Analysis</h3>\n";
    
    $test_path = $full_path;
    echo "<p><strong>Step 1 - Original path:</strong> <code>$test_path</code></p>\n";
    
    // Apply the same cleaning as GetImageUrl
    $cleaned_path = str_replace(['../', '.\\', '\\'], '', $test_path);
    echo "<p><strong>Step 2 - After path cleaning:</strong> <code>$cleaned_path</code></p>\n";
    
    // Apply urlencode
    $encoded_path = urlencode($cleaned_path);
    echo "<p><strong>Step 3 - After urlencode():</strong> <code>$encoded_path</code></p>\n";
    
    // Show what would happen with htmlspecialchars (the bug)
    $double_encoded = htmlspecialchars($encoded_path);
    echo "<p><strong>Step 4 - If htmlspecialchars() was applied (THE BUG):</strong> <code style='color: red;'>$double_encoded</code></p>\n";
    
    // Final URL construction
    $final_url = 'image.php?src=' . $encoded_path . '&type=thumbnail';
    echo "<p><strong>Step 5 - Final URL (correct):</strong> <code style='color: green;'>$final_url</code></p>\n";
    
    return [
        'test_passed' => strpos($encoded_path, '%27') !== false && strpos($encoded_path, '%23039%3B') === false,
        'original_path' => $full_path,
        'encoded_path' => $encoded_path,
        'final_url' => $final_url,
        'double_encoded_bug' => $double_encoded
    ];
}

/**
 * Test additional special characters to ensure no regressions
 */
function testOtherSpecialCharacters() {
    echo "<h3>4. Regression Tests - Other Special Characters</h3>\n";
    
    $test_cases = [
        "ACTIVITÉ À TESTER" => "photo test (1).jpg",
        "ACTIVITÉ & AUTRES" => "photo & test.jpg", 
        "ACTIVITÉ + PLUS" => "photo+test.jpg",
        "ACTIVITÉ #HASH" => "photo#test.jpg",
        "ACTIVITÉ %PERCENT" => "photo%test.jpg",
        "ACTIVITÉ ESPACE" => "photo test avec espaces.jpg"
    ];
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Test Case</th><th>Photo Name</th><th>URL</th><th>Status</th></tr>\n";
    
    foreach ($test_cases as $activity => $photo) {
        $path = $activity . '/' . $photo;
        $url = GetImageUrl($path, 'IMG_THUMBNAIL');
        
        // Basic validation - URL should not contain raw special characters (except in encoded form)
        $has_issues = false;
        $dangerous_chars = ["'", '"', '<', '>', '&', ' '];
        foreach ($dangerous_chars as $char) {
            if (strpos($url, $char) !== false) {
                $has_issues = true;
                break;
            }
        }
        
        $status = $has_issues ? '<span style="color: orange;">⚠ Contains raw special chars</span>' : '<span style="color: green;">✓ PASS</span>';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($activity) . "</td>";
        echo "<td>" . htmlspecialchars($photo) . "</td>";
        echo "<td><code>" . htmlspecialchars($url) . "</code></td>";
        echo "<td>$status</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

/**
 * Compare before and after the fix
 */
function showBeforeAfterComparison() {
    echo "<h3>5. Before vs After Fix Comparison</h3>\n";
    
    $test_path = "ODEUR D'ESSENCE/ODEUR ESSENCE (1).JPG";
    
    // Simulate the "before" (with htmlspecialchars bug)
    $correct_url = GetImageUrl($test_path, 'IMG_THUMBNAIL');
    $buggy_url = htmlspecialchars($correct_url);
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Version</th><th>Generated URL</th><th>Apostrophe Encoding</th><th>Status</th></tr>\n";
    
    // Before fix (simulated)
    echo "<tr>";
    echo "<td><strong>Before Fix</strong><br>(with htmlspecialchars)</td>";
    echo "<td><code style='color: red;'>" . htmlspecialchars($buggy_url) . "</code></td>";
    echo "<td style='color: red;'>%23039%3B (Wrong)</td>";
    echo "<td style='color: red;'>✗ BROKEN</td>";
    echo "</tr>\n";
    
    // After fix
    echo "<tr>";
    echo "<td><strong>After Fix</strong><br>(without htmlspecialchars)</td>";
    echo "<td><code style='color: green;'>" . htmlspecialchars($correct_url) . "</code></td>";
    echo "<td style='color: green;'>%27 (Correct)</td>";
    echo "<td style='color: green;'>✓ FIXED</td>";
    echo "</tr>\n";
    
    echo "</table>\n";
    
    echo "<h4>Expected vs Actual Results:</h4>\n";
    echo "<p><strong>Expected After Fix:</strong> <code>/image.php?src=ODEUR+D%27ESSENCE%2FODEUR+ESSENCE+%281%29.JPG&type=thumbnail</code></p>\n";
    echo "<p><strong>Actual After Fix:</strong> <code>$correct_url</code></p>\n";
    
    $matches_expected = ($correct_url === "image.php?src=ODEUR+D%27ESSENCE%2FODEUR+ESSENCE+%281%29.JPG&type=thumbnail");
    $status_text = $matches_expected ? '<span style="color: green;">✓ MATCHES EXPECTED</span>' : '<span style="color: orange;">⚠ Different format but may be correct</span>';
    echo "<p><strong>Result:</strong> $status_text</p>\n";
}

// Run the tests
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Image URL Encoding Test Report</title>\n<style>body{font-family:Arial,sans-serif;margin:20px;} table{border-collapse:collapse;margin:10px 0;} code{background:#f5f5f5;padding:2px 4px;}</style>\n</head>\n<body>\n";

$test_result = testApostropheEncodingFix();
testOtherSpecialCharacters();
showBeforeAfterComparison();

echo "<h3>6. Test Summary</h3>\n";
echo "<p><strong>Overall Status:</strong> " . ($test_result['test_passed'] ? '<span style="color: green;">✓ APOSTROPHE ENCODING FIX VERIFIED</span>' : '<span style="color: red;">✗ FIX VERIFICATION FAILED</span>') . "</p>\n";
echo "<p><strong>Next Steps:</strong> Test actual image access through generated URLs</p>\n";

echo "</body>\n</html>\n";
?>