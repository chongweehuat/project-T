//+------------------------------------------------------------------+
//|                                                      library.mq5 |
//|                                  Copyright 2022, MetaQuotes Ltd. |
//|                                             https://www.mql5.com |
//+------------------------------------------------------------------+
#property copyright "Copyright 2022, MetaQuotes Ltd."
#property link      "https://www.mql5.com"
#property version   "1.00"

bool UpdateTradeConfig(){
   string post_data = StringFormat(
      "account_id=%d&magic_number=%d&pair=%s&order_type=%s&remark=%s&",
      m_account.Login(),       // Account ID
      Inp_magic,               // Magic number
      Inp_pair,                // Pair (from input)
      Inp_dir,                  // Order type (buy/sell)
      Inp_remark               // EA version and MT terminal info
   );

   string headers = "";
   char   data[], result[];
   string resultHeaders;

   StringToCharArray(post_data, data);
   ResetLastError();

   // 5. 发送 WebRequest
   int response_code = WebRequest("POST", server_url, headers, 2000, data, result, resultHeaders);
   if (response_code > 0)
   {
      string response = CharArrayToString(result);
      
      // 解析 JSON 响应
      if (response_code == 200)
      {
         if(Inp_show_print)Print("Update successfully.");
         return true; // 请求成功，返回 true
      }
      else
      {
         if(Inp_show_print)Print("Failed to update configuration. Response: ", response);
         return false; // 请求失败，返回 false
      }
   }
   else
   {
      if(Inp_show_print)Print("Failed to connect to server. Error: ", GetLastError());
      return false; // 连接失败，返回 false
   }
}

//+------------------------------------------------------------------+
//| Request authorization configuration                              |
//+------------------------------------------------------------------+
bool RequestAuthorization(bool forceRequest=false)
{
   // 1. 检查是否处于回测模式
   if (Inp_skip_auth)
   {
      if(Inp_show_print)Print("Authorization skipped due to backtest mode.");
      authConfig.auth_FT = 1;
      authConfig.auth_AT = 1;
      authConfig.auth_CP = 1;
      authConfig.auth_SL = 1;
      authConfig.auth_CL = 1;
      return true; // 在回测模式下，始终返回 true
   }

   // 2. 检查请求时间间隔
   if (!forceRequest || TimeCurrent() - last_auth_request_time < auth_request_interval)
   {
      if(Inp_show_print)Print("Authorization request skipped due to interval limit.");
      return true; // 时间间隔未到，返回 true
   }

   // 3. 更新最后请求时间
   last_auth_request_time = TimeCurrent();

   // 4. 构建 POST 数据
   string post_data = StringFormat(
      "account_id=%d&magic_number=%d&pair=%s&order_type=%s&remark=%s&",
      m_account.Login(),       // Account ID
      Inp_magic,               // Magic number
      Inp_pair,                // Pair (from input)
      Inp_dir,                  // Order type (buy/sell)
      Inp_remark               // EA version and MT terminal info
   );

   string headers = "";
   char   data[], result[];
   string resultHeaders;

   StringToCharArray(post_data, data);
   ResetLastError();

   // 5. 发送 WebRequest
   int response_code = WebRequest("POST", server_auth_url, headers, 2000, data, result, resultHeaders);
   if (response_code > 0)
   {
      string response = CharArrayToString(result);
      if(Inp_show_print)Print("Authorization response: ", response);

      // 解析 JSON 响应
      if (response_code == 200 && ParseAuthorizationResponse(response))
      {
         if(Inp_show_print)Print("Authorization configuration retrieved successfully.");
         return true; // 请求成功，返回 true
      }
      else
      {
         if(Inp_show_print)Print("Failed to retrieve authorization configuration. Response: ", response);
         return false; // 请求失败，返回 false
      }
   }
   else
   {
      if(Inp_show_print)Print("Failed to connect to server. Error: ", GetLastError());
      return false; // 连接失败，返回 false
   }
}

//+------------------------------------------------------------------+
//| Parse authorization response                                     |
//+------------------------------------------------------------------+
bool ParseAuthorizationResponse(string response)
{
   // 示例响应: {"auth_FT":1, "auth_AT":0, "auth_CP":1, "auth_SL":1, "auth_CL":0}
   int start;

   start = StringFind(response, "\"auth_FT\":");
   authConfig.auth_FT = (start >= 0) ? StringToInteger(StringSubstr(response, start + 10, 1)) : 0;

   start = StringFind(response, "\"auth_AT\":");
   authConfig.auth_AT = (start >= 0) ? StringToInteger(StringSubstr(response, start + 10, 1)) : 0;

   start = StringFind(response, "\"auth_CP\":");
   authConfig.auth_CP = (start >= 0) ? StringToInteger(StringSubstr(response, start + 10, 1)) : 0;

   start = StringFind(response, "\"auth_SL\":");
   authConfig.auth_SL = (start >= 0) ? StringToInteger(StringSubstr(response, start + 10, 1)) : 0;

   start = StringFind(response, "\"auth_CL\":");
   authConfig.auth_CL = (start >= 0) ? StringToInteger(StringSubstr(response, start + 10, 1)) : 0;

   return true; // 假定解析成功
}

void hgpcAverage(
   string sPair,
   ENUM_TIMEFRAMES tf,
   int period,
   string &curDir,
   int &xCount,
   double &curPrice,   
   double &xHeight,
   double &curHeight,
   double &xPercent,
   int &bottomPos,
   int &topPos,
   double &minPrice,
   double &maxPrice,
   int &xWidth,
   double &avrgHeight
){
   MqlRates xrates[];
   ArraySetAsSeries(xrates,true);
   
   m_symbol.Name(sPair);
   m_symbol.RefreshRates();
      
   CopyRates(sPair,tf,0,period,xrates);
   
   double xminPrice=xrates[0].low;
   double xmaxPrice=xrates[0].high;
   
   int xwidthMin=-1;
   int xwidthMax=-1;
   
   curDir="up"; 
   for(int j=0; j<ArraySize(xrates); j++){
      bool isCross=(xrates[j].low<=m_symbol.Bid() && xrates[j].high>=m_symbol.Bid()) || (xrates[j].low<=m_symbol.Ask() && xrates[j].high>=m_symbol.Ask());
      if(isCross){
         xCount++;
         
         if(xwidthMin==-1)xwidthMin=j;
         if(xwidthMax==-1)xwidthMax=j;
         
         if(xwidthMin>j)xwidthMin=j;
         if(xwidthMax<j)xwidthMax=j;
      }
      
      if(xrates[j].low<xminPrice){
         xminPrice=xrates[j].low;
         bottomPos=j;
      }
      if(xrates[j].high>xmaxPrice){
         xmaxPrice=xrates[j].high;
         topPos=j;
      }
      avrgHeight+=xrates[j].high-xrates[j].low;
   }
   avrgHeight=avrgHeight/(period*m_symbol.Point());
   
   if(m_symbol.Bid()<xminPrice){
      xminPrice=m_symbol.Bid();
   }
   if(m_symbol.Ask()>xmaxPrice){
      xmaxPrice=m_symbol.Ask();
   }
   
   curPrice=(m_symbol.Bid()+m_symbol.Ask())/2;
   
   xHeight=(xmaxPrice-xminPrice)/m_symbol.Point();
   curHeight=(curPrice-xminPrice)/m_symbol.Point();
   xPercent=100*curHeight/xHeight;
   
   if(xPercent>50){
      curPrice=m_symbol.Ask();
      
      curHeight=(curPrice-xminPrice)/m_symbol.Point();
      xPercent=100*curHeight/xHeight;
   }else{
      curPrice=m_symbol.Bid();
      
      curHeight=(curPrice-xminPrice)/m_symbol.Point();
      xPercent=100*curHeight/xHeight;
   }
   
   if(topPos<bottomPos)curDir="down";
   minPrice=xminPrice;
   maxPrice=xmaxPrice;
   xWidth=xwidthMax-xwidthMin;
}