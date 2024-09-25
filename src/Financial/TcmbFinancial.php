<?php

namespace Financial;

class TcmbFinancial
{
    private $exchangeRateUrl = "https://www.tcmb.gov.tr/kurlar/today.xml";
    private $historicUrl = "https://www.tcmb.gov.tr/kurlar/";
    private $interestRateUrl = "https://www.tcmb.gov.tr/interest-rates.xml";
    private $data;

    public function __construct()
    {
        $this->fetchData();
    }

    // Bugünkü döviz verisini çek
    private function fetchData()
    {
        try {
            $xml = simplexml_load_file($this->exchangeRateUrl);
            $this->data = json_decode(json_encode($xml), true);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch exchange rates from TCMB.");
        }
    }

    // Belirli bir para biriminin döviz kuru bilgisini almak için kullanılacak fonksiyon
    public function getCurrencyRate($currencyCode)
    {
        if (!$this->data) {
            throw new \Exception("No exchange rate data available.");
        }

        foreach ($this->data['Currency'] as $currency) {
            if ($currency['@attributes']['CurrencyCode'] === strtoupper($currencyCode)) {
                return [
                    'CurrencyCode' => $currency['@attributes']['CurrencyCode'],
                    'Unit' => $currency['Unit'],
                    'ForexBuying' => $currency['ForexBuying'],
                    'ForexSelling' => $currency['ForexSelling']
                ];
            }
        }

        throw new \Exception("Currency code not found.");
    }

    // Tarihe göre döviz kuru çekme
    public function getCurrencyRateByDate($currencyCode, $date)
    {
        $formattedDate = date('Ymd', strtotime($date));
        $url = $this->historicUrl . $formattedDate . ".xml";
        try {
            $xml = simplexml_load_file($url);
            $data = json_decode(json_encode($xml), true);
            return $this->getCurrencyRateFromData($currencyCode, $data);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch historical exchange rates.");
        }
    }

    // Para birimi verilerini işleme fonksiyonu
    private function getCurrencyRateFromData($currencyCode, $data)
    {
        foreach ($data['Currency'] as $currency) {
            if ($currency['@attributes']['CurrencyCode'] === strtoupper($currencyCode)) {
                return [
                    'CurrencyCode' => $currency['@attributes']['CurrencyCode'],
                    'Unit' => $currency['Unit'],
                    'ForexBuying' => $currency['ForexBuying'],
                    'ForexSelling' => $currency['ForexSelling']
                ];
            }
        }
        throw new \Exception("Currency code not found.");
    }

    // Döviz dönüşümü yapma
    public function convertCurrency($amount, $fromCurrency, $toCurrency)
    {
        $fromRate = $this->getCurrencyRate($fromCurrency);
        $toRate = $this->getCurrencyRate($toCurrency);

        if (!$fromRate || !$toRate) {
            throw new \Exception("Currency conversion rates not found.");
        }

        // Dönüşüm işlemi
        $convertedAmount = ($amount * $fromRate['ForexBuying']) / $toRate['ForexBuying'];
        return $convertedAmount;
    }

    // Bir tarihteki döviz kurlarını karşılaştırma
    public function compareCurrencies($currencyCodes = [])
    {
        $results = [];
        foreach ($currencyCodes as $currencyCode) {
            $results[] = $this->getCurrencyRate($currencyCode);
        }
        return $results;
    }

    // En yüksek ve en düşük döviz kurları
    public function getHighestAndLowestRate($currencyCode, $startDate, $endDate)
    {
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        $highestRate = null;
        $lowestRate = null;

        while ($startDate <= $endDate) {
            $date = date('Y-m-d', $startDate);
            try {
                $rate = $this->getCurrencyRateByDate($currencyCode, $date);
                if (!$highestRate || $rate['ForexSelling'] > $highestRate) {
                    $highestRate = $rate['ForexSelling'];
                }
                if (!$lowestRate || $rate['ForexSelling'] < $lowestRate) {
                    $lowestRate = $rate['ForexSelling'];
                }
            } catch (\Exception $e) {
                // Veriler bulunamazsa hata vermeden devam et
            }
            $startDate = strtotime('+1 day', $startDate);
        }

        return [
            'highest' => $highestRate,
            'lowest' => $lowestRate
        ];
    }

    // Faiz oranlarını çekme
    public function getInterestRates()
    {
        try {
            $xml = simplexml_load_file($this->interestRateUrl);
            $data = json_decode(json_encode($xml), true);
            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch interest rates from TCMB.");
        }
    }
}
