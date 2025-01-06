//+------------------------------------------------------------------+
//|                                                      SAInfoCollector.mq5 |
//|                 Combined Trade & Account Info Collector                  |
//|                        Copyright 2025, Your Company                      |
//|                        https://sapi.my369.click                         |
//+------------------------------------------------------------------+
#property copyright "Copyright 2025, Your Company"
#property link      "https://sapi.my369.click"
#property version   "1.0"

#include <Trade\Trade.mqh>
#include <Trade\PositionInfo.mqh>

// Input Parameters
input string EA_Version   = "1.0"; // EA Version
input string TradeEndpointURL = "http://172.19.0.15:86/TradeInfoCollector.php"; // Trade Endpoint
input string AccountEndpointURL = "http://172.19.0.15:86/AccountInfoCollector.php"; // Account Endpoint
//input string TradeEndpointURL = "https://sapi.my369.click/TradeInfoCollector.php"; // Trade Endpoint
//input string AccountEndpointURL = "https://sapi.my369.click/AccountInfoCollector.php"; // Account Endpoint
input int    Timeout      = 5000;  // Timeout for WebRequest in milliseconds
input int    TradeTimeGapSec = 3; // Minimum time gap between trade info requests in seconds
input int    AccountTimeGapSec = 10; // Minimum time gap between account info requests in seconds
input double AccountChangePercent = 0.1; // Trigger percentage for account equity change
input bool   debug        = false; // Debug mode

// Global Variables
CPositionInfo m_position;      // Position object
CTrade m_trade;                // Trade object
datetime lastTradeRequestTime = 0; // Timestamp for last trade info request
datetime lastAccountUpdate = 0;   // Timestamp for last account update
double lastEquity = 0.0;          // Last recorded equity
string lastAccountStatus = "N/A"; // Last account transmission status

// Monitoring Statistics
int totalTradesSent    = 0;
int successTradesCount = 0;
int errorTradesCount   = 0;

//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
int OnInit()
{
    UpdateChartStatus();
    return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
    ClearChartStatus();
}

//+------------------------------------------------------------------+
//| Expert tick function                                             |
//+------------------------------------------------------------------+
void OnTick()
{
    // Handle Trade Info
    if (TimeCurrent() - lastTradeRequestTime >= TradeTimeGapSec)
    {
        SendAllTrades();
        lastTradeRequestTime = TimeCurrent();
    }

    // Handle Account Info
    double equity = AccountInfoDouble(ACCOUNT_EQUITY);
    double changePercent = lastEquity > 0 ? MathAbs(equity - lastEquity) / lastEquity * 100 : 0;

    if (TimeCurrent() - lastAccountUpdate >= AccountTimeGapSec || changePercent >= AccountChangePercent)
    {
        lastEquity = equity;
        lastAccountUpdate = TimeCurrent();
        SendAccountInfo();
    }

    UpdateChartStatus();
}

//+------------------------------------------------------------------+
//| Sends all open trades in a single WebRequest                     |
//+------------------------------------------------------------------+
void SendAllTrades()
{
    int totalTrades = PositionsTotal();
    if (totalTrades == 0)
    {
        if (debug)
            Print("No open trades to send.");
        return;
    }

    string payload = BuildAllTradesPayload();
    if (StringLen(payload) == 0)
    {
        Print("Failed to build trade payload.");
        return;
    }

    string headers = "";
    char   data[], response[];
    string resultHeaders;

    StringToCharArray(payload, data);
    ResetLastError();
    int responseCode = WebRequest("POST", TradeEndpointURL, headers, Timeout, data, response, resultHeaders);
    totalTradesSent++;

    if (responseCode == -1)
    {
        errorTradesCount++;
        Print("Trade WebRequest failed. Error: ", GetLastError());
        return;
    }

    string responseText = CharArrayToString(response);
    if (responseCode == 200)
    {
        successTradesCount++;
        if (debug)
            Print("Trade info sent successfully.");
    }
    else
    {
        errorTradesCount++;
        Print("Failed to send trade info. Response: ", responseText);
    }
}

//+------------------------------------------------------------------+
//| Builds the POST payload for all open trades                      |
//+------------------------------------------------------------------+
string BuildAllTradesPayload()
{
    int totalTrades = PositionsTotal();
    if (totalTrades == 0)
        return "";

    string payload = "ea_version=" + EA_Version + "&";
    payload += "account_id=" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN)) + "&";

    for (int i = 0; i < totalTrades; i++)
    {
        if (m_position.SelectByIndex(i))
        {
            string tradeData = "trades[]=";
            tradeData += "ticket=" + (string)m_position.Ticket() + "|";
            tradeData += "pair=" + m_position.Symbol() + "|";
            tradeData += "order_type=" + m_position.TypeDescription() + "|";
            tradeData += "volume=" + DoubleToString(m_position.Volume(), 2) + "|";
            tradeData += "profit=" + DoubleToString(m_position.Profit(), 2) + "|";
            tradeData += "open_price=" + DoubleToString(m_position.PriceOpen(), 5) + "|";
            tradeData += "open_time=" + TimeToString(m_position.Time(), TIME_DATE | TIME_MINUTES) + "|";
            tradeData += "magic_number=" + IntegerToString((int)m_position.Magic());
            payload += tradeData + "&";
        }
    }

    return payload;
}

//+------------------------------------------------------------------+
//| Sends the account information to the endpoint                   |
//+------------------------------------------------------------------+
void SendAccountInfo()
{
    string payload = BuildAccountInfo();
    string headers = "Content-Type: application/x-www-form-urlencoded\r\n";
    char data[], response[];
    string resultHeaders;

    StringToCharArray(payload, data);
    int responseCode = WebRequest("POST", AccountEndpointURL, headers, Timeout, data, response, resultHeaders);

    if (responseCode == -1)
    {
        lastAccountStatus = "Failed: Error " + IntegerToString(GetLastError());
    }
    else
    {
        string responseText = CharArrayToString(response);
        if (StringFind(responseText, "\"status\":\"success\"") >= 0)
            lastAccountStatus = "Success";
        else
            lastAccountStatus = "Failed: Unexpected response";
    }
}

//+------------------------------------------------------------------+
//| Builds the query string for account information                  |
//+------------------------------------------------------------------+
string BuildAccountInfo()
{
    string accountNumber = IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN));
    string accountName = AccountInfoString(ACCOUNT_NAME);
    string brokerName = AccountInfoString(ACCOUNT_COMPANY);
    long leverage = AccountInfoInteger(ACCOUNT_LEVERAGE);
    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double equity = AccountInfoDouble(ACCOUNT_EQUITY);
    double freeMargin = AccountInfoDouble(ACCOUNT_MARGIN_FREE);

    string payload = "ea_version=" + EA_Version + "&";
    payload += "account_number=" + accountNumber + "&";
    payload += "account_name=" + accountName + "&";
    payload += "broker_name=" + brokerName + "&";
    payload += "leverage=" + IntegerToString(leverage) + "&";
    payload += "balance=" + DoubleToString(balance, 2) + "&";
    payload += "equity=" + DoubleToString(equity, 2) + "&";
    payload += "free_margin=" + DoubleToString(freeMargin, 2);

    return payload;
}

//+------------------------------------------------------------------+
//| Updates chart monitoring status                                  |
//+------------------------------------------------------------------+
void UpdateChartStatus()
{
    string status = "SAInfoCollector - Monitoring\n";
    status += "EA Version: " + EA_Version + "\n";
    status += "Trade Endpoint: " + TradeEndpointURL + "\n";
    status += "Account Endpoint: " + AccountEndpointURL + "\n";
    status += "Last Equity: " + DoubleToString(lastEquity, 2) + "\n";
    status += "Last Account Update: " + TimeToString(lastAccountUpdate, TIME_DATE | TIME_MINUTES) + "\n";
    status += "Last Account Status: " + lastAccountStatus + "\n";
    status += "Total Trades Sent: " + IntegerToString(totalTradesSent) + "\n";
    status += "Successful Trades: " + IntegerToString(successTradesCount) + "\n";
    status += "Error Trades: " + IntegerToString(errorTradesCount);

    Comment(status);
}

//+------------------------------------------------------------------+
//| Clears chart monitoring status                                   |
//+------------------------------------------------------------------+
void ClearChartStatus()
{
    Comment("");
}

//+------------------------------------------------------------------+
