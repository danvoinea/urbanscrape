# urbanscrape
Urbanspoon Parallel PHP Curl Xpath Scraper.

usage:
set_time_limit(0);

$scraper = new urbanScraper();

$list = $scraper->doPages("http://www.urbanspoon.com/lb/3/best-restaurants-New-York"); 

$restaurants = $scraper->displayRestaurantDetailsMulti($list);

$scraper->print_csv($restaurants);



You enter the city you want to save and it will generate a CSV file. Use with caution.
