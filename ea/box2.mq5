//+------------------------------------------------------------------+
//| 操作时段评估EA                                                  |
//+------------------------------------------------------------------+

// 输入参数
input double BoxTop = 1.2000;                 // 箱体顶部价格
input double BoxBottom = 1.1900;              // 箱体底部价格
input string Direction = "Buy";               // 交易方向 ("Buy" 或 "Sell")
input double MaxRisk = 0.02;                  // 最大风险占比（账户资金）
input double ATRMultiplier = 1.5;             // ATR倍数（止损与追单间距）
input int ATRPeriod = 14;                     // ATR计算周期
input int RSIPeriod = 14;                     // RSI计算周期
input int RSILevel = 30;                      // RSI阈值（多单为30，空单为70）
input int TimeFrameHours = 1;                 // 评估时间段长度（单位：小时）

// 全局变量
double ATRValue;
double TotalProfitByHour[24];                 // 按小时统计总收益
int TotalTradesByHour[24];                    // 按小时统计总交易数
int WinningTradesByHour[24];                  // 按小时统计盈利交易数

//+------------------------------------------------------------------+
//| 初始化                                                          |
//+------------------------------------------------------------------+
int OnInit() {
   ATRValue = iATR(NULL, 0, ATRPeriod);       // 初始化ATR
   ArrayInitialize(TotalProfitByHour, 0);    // 初始化收益数组
   ArrayInitialize(TotalTradesByHour, 0);    // 初始化交易次数数组
   ArrayInitialize(WinningTradesByHour, 0);  // 初始化盈利交易次数数组
   Print("EA Initialized. BoxTop: ", BoxTop, ", BoxBottom: ", BoxBottom);
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| 回测时调用                                                      |
//+------------------------------------------------------------------+
void OnTick() {
   static datetime lastTradeTime = 0;

   // 只在时间框架变化时处理逻辑
   if (TimeCurrent() - lastTradeTime < TimeFrameHours * 3600)
      return;

   lastTradeTime = TimeCurrent();
   int currentHour = TimeHour(TimeCurrent());

   // 判断是否满足开单条件
   double RSIValue = iRSI(NULL, 0, RSIPeriod, PRICE_CLOSE, 0);
   double CurrentPrice = (StringCompare(Direction, "Buy") == 0) ? Bid : Ask;

   if (CheckOpenCondition(RSIValue, CurrentPrice)) {
      // 模拟开单
      double StopLoss = CalculateStopLoss(CurrentPrice);
      double Profit = SimulateTrade(CurrentPrice, StopLoss);

      // 更新统计
      TotalTradesByHour[currentHour]++;
      TotalProfitByHour[currentHour] += Profit;
      if (Profit > 0) WinningTradesByHour[currentHour]++;
   }
}

//+------------------------------------------------------------------+
//| 判断是否满足开单条件                                            |
//+------------------------------------------------------------------+
bool CheckOpenCondition(double RSIValue, double CurrentPrice) {
   if (StringCompare(Direction, "Buy") == 0 && RSIValue < RSILevel) {
      return true;
   }
   if (StringCompare(Direction, "Sell") == 0 && RSIValue > 100 - RSILevel) {
      return true;
   }
   return false;
}

//+------------------------------------------------------------------+
//| 模拟交易并计算收益                                              |
//+------------------------------------------------------------------+
double SimulateTrade(double EntryPrice, double StopLoss) {
   double TakeProfit;
   if (StringCompare(Direction, "Buy") == 0) {
      TakeProfit = BoxTop - ATRValue * ATRMultiplier;
   } else {
      TakeProfit = BoxBottom + ATRValue * ATRMultiplier;
   }

   double Profit = 0;
   if (StringCompare(Direction, "Buy") == 0) {
      Profit = MathMin(TakeProfit - EntryPrice, EntryPrice - StopLoss);
   } else {
      Profit = MathMin(EntryPrice - TakeProfit, StopLoss - EntryPrice);
   }
   return Profit;
}

//+------------------------------------------------------------------+
//| 计算止损位置                                                   |
//+------------------------------------------------------------------+
double CalculateStopLoss(double EntryPrice) {
   if (StringCompare(Direction, "Buy") == 0) {
      return BoxBottom - ATRValue * ATRMultiplier;
   } else {
      return BoxTop + ATRValue * ATRMultiplier;
   }
}

//+------------------------------------------------------------------+
//| 回测结束时输出评估结果                                          |
//+------------------------------------------------------------------+
void OnTester() {
   Print("==== Hourly Performance Evaluation ====");
   for (int i = 0; i < 24; i++) {
      double winRate = (TotalTradesByHour[i] > 0) ? 
                       (WinningTradesByHour[i] * 100.0 / TotalTradesByHour[i]) : 0;
      double avgProfit = (TotalTradesByHour[i] > 0) ? 
                         (TotalProfitByHour[i] / TotalTradesByHour[i]) : 0;

      Print("Hour ", i, ": Trades = ", TotalTradesByHour[i], 
            ", Win Rate = ", DoubleToString(winRate, 2), "%, Avg Profit = ", 
            DoubleToString(avgProfit, 2));
   }

   // 找到最优操作时段
   int bestHour = 0;
   double maxProfit = -DBL_MAX;
   for (int i = 0; i < 24; i++) {
      if (TotalProfitByHour[i] > maxProfit) {
         maxProfit = TotalProfitByHour[i];
         bestHour = i;
      }
   }
   Print("Best Hour: ", bestHour, ", Max Profit: ", DoubleToString(maxProfit, 2));
}
