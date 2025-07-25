<?php
require(__DIR__ . "/../../partials/nav.php");

//ng569 7/14/25
//changed endpoints and API Host. Set up the API key under TIME_API_KEY
//Changed PHP Output since the API returns everything under one array rather than a multidimentional one

$result = [];
if (isset($_GET["location"])) {
    //function=GLOBAL_QUOTE&symbol=MSFT&datatype=json
    //"function" => "GLOBAL_QUOTE", "symbol" => $_GET["symbol"], "datatype" => "json"
    $data = ["location" => $_GET["location"]];
    $endpoint = "https://world-time-by-based-api.p.rapidapi.com/v1/worldtime/";
    $isRapidAPI = true;
    $rapidAPIHost = "world-time-by-based-api.p.rapidapi.com";
    $result = get($endpoint, "TIME_API_KEY", $data, $isRapidAPI, $rapidAPIHost);
    //example of cached data to save the quotas, don't forget to comment out the get() if using the cached data for testing
    /*$result = ["status" => 200, "response" => '{
        "datetime":"2025-07-14 99:99:99",
        "timezone_name":"Eastern Daylight Time",
        "timezone_location":"America/New_York",
        "timezone_abbreviation":"EDT",
        "gmt_offset":-4,
        "is_dst":true,
        "requested_location":"New York",
        "latitude":40.7127281,
        "longitude":-74.0060152
    }'];*/
    /*
    timezone:"America/New_York"
    datetime:"2025-07-22 01:37:33"
    date:"2025-07-22"
    year:"2025"
    month:"07"
    day:"22"
    hour:"01"
    minute:"37"
    second:"33"
    day_of_week:"Tuesday"*/
    error_log("Response: " . var_export($result, true));
    if (se($result, "status", 400, false) == 200 && isset($result["response"])) {
        $result = json_decode($result["response"], true);
    } else {
        $result = [];
    }
}
?>
<div class="container-fluid">
    <h1>Time Info</h1>
    <form>
        <div>
            <label>Location of User</label>
            <input name="location" />
            <input type="submit" value="Fetch Location" />
        </div>
    </form>
    <div class="row ">
        <?php if (isset($result)) : ?>
            <?php foreach ($result as $title => $timeVal) : ?>
                <pre>
                    <?php 
                        $str = $title . ": " . $timeVal;
                        var_export($str);
                    ?>
                </pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
require(__DIR__ . "/../../partials/flash.php");
