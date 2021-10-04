<?php

libxml_use_internal_errors(true);
libxml_disable_entity_loader(true);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('error_reporting', E_ALL);

require_once 'vendor/autoload.php';

use Curl\Curl;

/**
 * consts
 */

$detailsPageUrlTemplate = "https://search.ipaustralia.gov.au/trademarks/search/view/";

/**
 * get word as a parameter
 * checking if there is any given
 */

if ($argc != 2) {
	exit ("The parameter either is not given or there are redundant parameters");
} else {
	$word = $argv[1];
	echo "Searching for " . $word . " trademark name" . PHP_EOL;
}

/** 
 * get cookies from search/advanced
 * set static cookies, get dynamic cookies, get location header, set post data variable
 */

$curlGetCookies = new Curl();
$curlGetCookies->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0');
$curlGetCookies->get('https://search.ipaustralia.gov.au/trademarks/search/advanced');

$cookiesDynamic = $curlGetCookies->getCookie("AWSALB");
$cookiesStatic = array(
	"XSRF-TOKEN" => $curlGetCookies->getCookie("XSRF-TOKEN"),
	"SESSIONTOKEN" => $curlGetCookies->getCookie("SESSIONTOKEN"),
	"JSESSIONID" => $curlGetCookies->getCookie("JSESSIONID")
);

unset($curlGetCookies);

$data = "_csrf=" . $cookiesStatic["XSRF-TOKEN"] . "&wv%5B0%5D=" . $word . "&wt%5B0%5D=PART&weOp%5B0%5D=AND&wv%5B1%5D=&wt%5B1%5D=PART&wrOp=AND&wv%5B2%5D=&wt%5B2%5D=PART&weOp%5B1%5D=AND&wv%5B3%5D=&wt%5B3%5D=PART&iv%5B0%5D=&it%5B0%5D=PART&ieOp%5B0%5D=AND&iv%5B1%5D=&it%5B1%5D=PART&irOp=AND&iv%5B2%5D=&it%5B2%5D=PART&ieOp%5B1%5D=AND&iv%5B3%5D=&it%5B3%5D=PART&wp=&_sw=on&classList=&ct=A&status=&dateType=LODGEMENT_DATE&fromDate=&toDate=&ia=&gsd=&endo=&nameField%5B0%5D=OWNER&name%5B0%5D=&attorney=&oAcn=&idList=&ir=&publicationFromDate=&publicationToDate=&i=&c=&originalSegment=";

$curlGetLocation = new Curl();
$curlGetLocation->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0');
$curlGetLocation->setCookie('AWSALBCORS', $cookiesDynamic);
$curlGetLocation->setCookie('AWSALB', $cookiesDynamic);
$curlGetLocation->setCookie('XSRF-TOKEN', $cookiesStatic["XSRF-TOKEN"]);
$curlGetLocation->setCookie('SESSIONTOKEN', $cookiesStatic["SESSIONTOKEN"]);
$curlGetLocation->setCookie('JSESSIONID', $cookiesStatic["JSESSIONID"]);
$curlGetLocation->setHeader('Content-Type', 'application/x-www-form-urlencoded');
$curlGetLocation->setHeader('Host', 'search.ipaustralia.gov.au');
$curlGetLocation->setHeader('Referer', 'https://search.ipaustralia.gov.au/trademarks/search/advanced');
$curlGetLocation->post('https://search.ipaustralia.gov.au/trademarks/search/doSearch', $data);

$cookiesDynamic = $curlGetLocation->getCookie("AWSALB");

$responseHeadersArray = (array) $curlGetLocation->responseHeaders;
unset($curlGetLocation);

$arrayKeys = array_keys($responseHeadersArray);
$pureUrl = $responseHeadersArray[$arrayKeys[0]]["location"];
$pageNumber = 0;
$url = $pureUrl . '&p=' . $pageNumber;

$curl = new Curl();
$curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0');
$curl->setHeader('Host', 'search.ipaustralia.gov.au');
$curl->setCookie('AWSALBCORS', $cookiesDynamic);
$curl->setCookie('AWSALB', $cookiesDynamic);
$curl->setCookie('XSRF-TOKEN', $cookiesStatic["XSRF-TOKEN"]);
$curl->setCookie('SESSIONTOKEN', $cookiesStatic["SESSIONTOKEN"]);
$curl->setCookie('JSESSIONID', $cookiesStatic["JSESSIONID"]);
$curl->get($pureUrl);
$cookiesDynamic = $curl->getCookie("AWSALB");

/**
 * representing an entire HTML document via domdoc
 * evaluating the given XPath expression and returning a typed result
 */

$domDoc = new DOMDocument();
$domDoc->preserveWhiteSpace = false;
$domDoc->loadHTML($curl->response);

$xPath = new DOMXPath($domDoc);
$resultsCount = $xPath->evaluate('//h2[@class="number qa-count"]');
$resultsCount = (int) preg_replace('/[^0-9]/', "", $resultsCount[0]->textContent);

unset($xPath);
unset($domDoc);
unset($curl);

/**
 * at this point we have first page url with 0 or more results
 * set dynamic cookies and get them every time we make request to the next page
 */ 

if ($resultsCount < 0) {
	exit ("Results count is less than zero, server error");
}

if ($resultsCount == 0) {
	exit ("No results");
}

$arrayOfResults = array();
$arrayOfResults[] = $resultsCount;

/**
 * if the results count is between 0 and 2000
 */

if ($resultsCount > 0 and $resultsCount < 2000) {

	while (true) {

		$curl = new Curl();
		if ($pageNumber == 0) {
			$curl->setHeader('Referer', $pureUrl);
		} else {
			$curl->setHeader('Referer', $pureUrl . '&p=' . ($pageNumber - 1));
		}
		$curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0');
		$curl->setHeader('Host', 'search.ipaustralia.gov.au');
		$curl->setCookie('AWSALBCORS', $cookiesDynamic);
		$curl->setCookie('AWSALB', $cookiesDynamic);
		$curl->setCookie('XSRF-TOKEN', $cookiesStatic["XSRF-TOKEN"]);
		$curl->setCookie('SESSIONTOKEN', $cookiesStatic["SESSIONTOKEN"]);
		$curl->setCookie('JSESSIONID', $cookiesStatic["JSESSIONID"]);
		$curl->get($pureUrl . '&p=' . $pageNumber);
		$cookiesDynamic = $curl->getCookie("AWSALB");

		$domDoc = new DOMDocument();
		$domDoc->preserveWhiteSpace = false;
		$domDoc->loadHTML($curl->response);
		$xPath = new DOMXPath($domDoc);

		$indexes  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="col c-5 table-index "]//span');
		$numbers  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="number"]//a');
		$logoUrls = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="trademark image"]//img/@src');
		$names    = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="trademark words"]');
		$classes  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="classes "]');
		$statuses = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="status"]//div//span');

		unset($xPath);
		unset($domDoc);
		unset($curl);

		$lastIndex = $indexes->item(($indexes->count() - 1))->textContent;

		foreach ($indexes as $key => $index) {

			$arrayOfResult = array();

			if (isset($numbers[$key]->textContent)) {
				$arrayOfResult["number"] = trim($numbers[$key]->textContent);
			} else {
				$arrayOfResult["number"] = "";
			}

			if (isset($logoUrls[$key]->textContent)) {
				$arrayOfResult["logo_url"] = $logoUrls[$key]->textContent;
			} else {
				$arrayOfResult["logo_url"] = "";
			}

			if (isset($names[$key]->textContent)) {
				$arrayOfResult["name"] = trim(preg_replace("/\s+/", " ", $names[$key]->textContent));
			} else {
				$arrayOfResult["name"] = "";
			}

			if (isset($classes[$key]->textContent)) {
				$arrayOfResult["classes"] = trim(preg_replace("/\s+/", " ", $classes[$key]->textContent));
			} else {
				$arrayOfResult["classes"] = "";
			}

			if (isset($statuses[$key]->textContent)) {
				$statusesUpdated = explode(":", trim($statuses[$key]->textContent));
				if (isset($statusesUpdated[0])) {
					$arrayOfResult["status1"] = trim($statusesUpdated[0]);
				} else {
					$arrayOfResult["status1"] = "";
				}
				if (isset($statusesUpdated[1])) {
					$arrayOfResult["status2"] = trim($statusesUpdated[1]);
				} else {
					$arrayOfResult["status2"] = "";
				}
			} else {
				$arrayOfResult["status1"] = "";
				$arrayOfResult["status2"] = "";
			}

			if (isset($numbers[$key]->textContent)) {
				$arrayOfResult["details_page_url"] = $detailsPageUrlTemplate . trim($numbers[$key]->textContent);
			} else {
				$arrayOfResult["details_page_url"] = "";
			}

			$arrayOfResults[$index->textContent] = $arrayOfResult;

		}

		if ($lastIndex == $resultsCount) {
			break;
		} else {
			$pageNumber++;
		}
	}

	var_dump($arrayOfResults);

	exit ($arrayOfResults[0] . " results displayed. " . $arrayOfResults[0] . " results found");
}

/**
 * if the results count is more than 2000, then only 2000 results are displayed by the website
 */

if ($resultsCount >= 2000) {

	for ($pagenumber = 0; $pagenumber < 20; $pagenumber++) {

		$curl = new Curl();
		if ($pagenumber == 0) {
			$curl->setHeader('Referer', $pureUrl);
		} else {
			$curl->setHeader('Referer', $pureUrl . '&p=' . ($pagenumber - 1));
		}
		$curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0');
		$curl->setHeader('Host', 'search.ipaustralia.gov.au');
		$curl->setCookie('AWSALBCORS', $cookiesDynamic);
		$curl->setCookie('AWSALB', $cookiesDynamic);
		$curl->setCookie('XSRF-TOKEN', $cookiesStatic["XSRF-TOKEN"]);
		$curl->setCookie('SESSIONTOKEN', $cookiesStatic["SESSIONTOKEN"]);
		$curl->setCookie('JSESSIONID', $cookiesStatic["JSESSIONID"]);
		$curl->get($pureUrl . '&p=' . $pagenumber);
		$cookiesDynamic = $curl->getCookie("AWSALB");

		$domDoc = new DOMDocument();
		$domDoc->preserveWhiteSpace = false;
		$domDoc->loadHTML($curl->response);
		$xPath = new DOMXPath($domDoc);

		$indexes  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="col c-5 table-index "]//span');
		$numbers  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="number"]//a');
		$logoUrls = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="trademark image"]//img/@src');
		$names    = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="trademark words"]');
		$classes  = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="classes "]');
		$statuses = $xPath->evaluate('//table[@class="fetch-table trademark-list"]//tbody//tr//td[@class="status"]//div//span');

		unset($xPath);
		unset($domDoc);
		unset($curl);

		$lastIndex = $indexes->item(($indexes->count() - 1))->textContent;

		foreach ($indexes as $key => $index) {

			$arrayOfResult = array();

			if (isset($numbers[$key]->textContent)) {
				$arrayOfResult["number"] = trim($numbers[$key]->textContent);
			} else {
				$arrayOfResult["number"] = "";
			}

			if (isset($logoUrls[$key]->textContent)) {
				$arrayOfResult["logo_url"] = $logoUrls[$key]->textContent;
			} else {
				$arrayOfResult["logo_url"] = "";
			}

			if (isset($names[$key]->textContent)) {
				$arrayOfResult["name"] = trim(preg_replace("/\s+/", " ", $names[$key]->textContent));
			} else {
				$arrayOfResult["name"] = "";
			}

			if (isset($classes[$key]->textContent)) {
				$arrayOfResult["classes"] = trim(preg_replace("/\s+/", " ", $classes[$key]->textContent));
			} else {
				$arrayOfResult["classes"] = "";
			}

			if (isset($statuses[$key]->textContent)) {
				$statusesUpdated = explode(":", trim($statuses[$key]->textContent));
				if (isset($statusesUpdated[0])) {
					$arrayOfResult["status1"] = trim($statusesUpdated[0]);
				} else {
					$arrayOfResult["status1"] = "";
				}
				if (isset($statusesUpdated[1])) {
					$arrayOfResult["status2"] = trim($statusesUpdated[1]);
				} else {
					$arrayOfResult["status2"] = "";
				}
			} else {
				$arrayOfResult["status1"] = "";
				$arrayOfResult["status2"] = "";
			}

			if (isset($numbers[$key]->textContent)) {
				$arrayOfResult["details_page_url"] = $detailsPageUrlTemplate . trim($numbers[$key]->textContent);
			} else {
				$arrayOfResult["details_page_url"] = "";
			}

			$arrayOfResults[$index->textContent] = $arrayOfResult;

		}
	}

	var_dump($arrayOfResults);

	exit ("2000 results displayed. " . $arrayOfResults[0] . " results found");
}
