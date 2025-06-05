<?php

require_once "base.php";

$ucid = "mt85"; // <-- set your ucid

// Don't edit the arrays below, they are used to test your code
$array1 = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
$array2 = [9, 8, 7, 6, 5, 4, 3, 2, 1, 0];
$array3 = [0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9];
$array4 = [9, 9, 8, 8, 7, 7, 6, 6, 5, 5, 4, 4, 3, 3, 2, 2, 1, 1, 0, 0];

function printOdds($arr, $arrayNumber)
{
    // Only make edits between the designated "Start" and "End" comments
    printArrayInfo($arr, $arrayNumber);

    // Challenge: Print odd values only in a single line separated by commas
    // Step 1: sketch out plan using comments (include ucid and date)
    // Step 2: Add/commit your outline of comments (required for full credit)
    // Step 3: Add code to solve the problem (add/commit as needed)

    echo "Output Array: ";
    // Start Solution Edits

    //ng569 06/05/2025
    //Step 1: Find all odd numbers.
        //All odd numbers are even numbers +1
        //So if element % 2 == 1 then the element is an odd number
        //Store in another array
    //Step 2: Print out odd number followed by comma
        //Print out only odd number array followe dby commas between each element except the last one
    
    $arrOdd = [];
    for ($i = 0; $i < count($arr); $i++) {
        if ($arr[$i] % 2 == 1) {
            array_push($arrOdd, $arr[$i]);
        }
    }

    for ($i = 0; $i < count($arrOdd); $i++) {
        if ((count($arrOdd) - 1) == $i) {
            echo "$arrOdd[$i]";
            break;
        }
        echo "$arrOdd[$i], ";
    }

    // End Solution Edits
    echo "<br>______________________________________<br>";
}

// Run the problem
printHeader($ucid, 1);
printOdds($array1, 1);
printOdds($array2, 2);
printOdds($array3, 3);
printOdds($array4, 4);
printFooter($ucid, 1);