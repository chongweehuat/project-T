//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
input double BoxTop = 1.2000;                 // 箱体顶部价格
input double BoxBottom = 1.1900;              // 箱体底部价格
input string Direction = "Buy";               // 交易方向 ("Buy" 或 "Sell")
input double MaxRisk = 0.02;                  // 最大风险占比（账户资金）
input double ATRMultiplier = 1.5;             // ATR倍数（止损与追单间距）
input int RSILevel = 30;                      // RSI阈值（多单为30，空单为70）
input double TakeProfitFactor = 0.9;          // 目标止盈比例
input double TrailingStopATRFactor = 1.0;     // 动态止盈ATR倍数
input double LotDecimals = 0.01;              // 仓位精度
input int ATRPeriod = 14;                     // ATR计算周期
input int RSIPeriod = 14;                     // RSI计算周期

double ATRValue;
double LastEntryPrice = 0;                    // 上次开单价格
double TargetProfit;                          // 目标止盈价
int DirectionType;                            // 交易方向类型（0 = Buy, 1 = Sell）

//+------------------------------------------------------------------+
//| 初始化                                                          |
//+------------------------------------------------------------------+
int OnInit() {
   ATRValue = iATR(NULL, 0, ATRPeriod);       // 计算ATR值
   DirectionType = (StringCompare(Direction, "Buy") == 0) ? 0 : 1;

   if (DirectionType == 0)
      TargetProfit = BoxTop - ATRValue * TakeProfitFactor;
   else
      TargetProfit = BoxBottom + ATRValue * TakeProfitFactor;

   Print("EA Initialized with BoxTop: ", BoxTop, ", BoxBottom: ", BoxBottom, 
         ", Direction: ", Direction, ", Target Profit: ", TargetProfit);
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| EA主逻辑                                                        |
//+------------------------------------------------------------------+
void OnTick() {
   double RSIValue = iRSI(NULL, 0, RSIPeriod, PRICE_CLOSE, 0);
   double CurrentPrice = (DirectionType == 0) ? Bid : Ask; // 当前价格

   // 检查是否满足开单条件
   if (CheckOpenCondition(RSIValue, CurrentPrice)) {
      double StopLoss = CalculateStopLoss(CurrentPrice);
      double LotSize = CalculateLotSize(CurrentPrice, StopLoss);
      OpenOrder(CurrentPrice, LotSize, StopLoss);
   }

   // 检查是否需要追单
   if (LastEntryPrice > 0 && CheckTrailingCondition(CurrentPrice)) {
      double StopLoss = CalculateStopLoss(CurrentPrice);
      double LotSize = CalculateLotSize(CurrentPrice, StopLoss) * 0.5; // 追单仓位减半
      OpenOrder(CurrentPrice, LotSize, StopLoss);
   }

   // 动态止盈更新
   UpdateTrailingStop();
}

//+------------------------------------------------------------------+
//| 检查开单条件                                                    |
//+------------------------------------------------------------------+
bool CheckOpenCondition(double RSIValue, double CurrentPrice) {
   if (DirectionType == 0 && RSIValue < RSILevel && (LastEntryPrice == 0 || CurrentPrice < LastEntryPrice + ATRValue * ATRMultiplier)) {
      return true;
   }
   if (DirectionType == 1 && RSIValue > 100 - RSILevel && (LastEntryPrice == 0 || CurrentPrice > LastEntryPrice - ATRValue * ATRMultiplier)) {
      return true;
   }
   return false;
}

//+------------------------------------------------------------------+
//| 计算止损                                                        |
//+------------------------------------------------------------------+
double CalculateStopLoss(double EntryPrice) {
   if (DirectionType == 0) {
      return BoxBottom - ATRValue * ATRMultiplier;
   } else {
      return BoxTop + ATRValue * ATRMultiplier;
   }
}

//+------------------------------------------------------------------+
//| 计算仓位大小                                                    |
//+------------------------------------------------------------------+
double CalculateLotSize(double EntryPrice, double StopLoss) {
   double StopLossDistance = MathAbs(EntryPrice - StopLoss);
   double LotSize = MaxRisk * AccountBalance() / (StopLossDistance * MarketInfo(Symbol(), MODE_TICKVALUE));
   return NormalizeDouble(LotSize, LotDecimals);
}

//+------------------------------------------------------------------+
//| 开单逻辑                                                        |
//+------------------------------------------------------------------+
void OpenOrder(double EntryPrice, double LotSize, double StopLoss) {
   if (LotSize < MarketInfo(Symbol(), MODE_MINLOT)) {
      Print("Lot size too small, skipping order.");
      return;
   }

   int ticket;
   if (DirectionType == 0) {
      ticket = OrderSend(Symbol(), OP_BUY, LotSize, EntryPrice, 3, StopLoss, TargetProfit, "Buy Order", 0, 0, Blue);
   } else {
      ticket = OrderSend(Symbol(), OP_SELL, LotSize, EntryPrice, 3, StopLoss, TargetProfit, "Sell Order", 0, 0, Red);
   }

   if (ticket > 0) {
      LastEntryPrice = EntryPrice; // 更新最后开单点
      Print("Order opened successfully. Entry: ", EntryPrice, ", LotSize: ", LotSize, ", StopLoss: ", StopLoss);
   } else {
      Print("Order failed: ", GetLastError());
   }
}

//+------------------------------------------------------------------+
//| 检查追单条件                                                    |
//+------------------------------------------------------------------+
bool CheckTrailingCondition(double CurrentPrice) {
   if (DirectionType == 0) {
      return CurrentPrice >= LastEntryPrice + ATRValue * ATRMultiplier;
   } else {
      return CurrentPrice <= LastEntryPrice - ATRValue * ATRMultiplier;
   }
}

//+------------------------------------------------------------------+
//| 动态止盈更新                                                    |
//+------------------------------------------------------------------+
void UpdateTrailingStop() {
   for (int i = OrdersTotal() - 1; i >= 0; i--) {
      if (OrderSelect(i, SELECT_BY_POS, MODE_TRADES) &&
          OrderType() == (DirectionType == 0 ? OP_BUY : OP_SELL)) {
         double TrailingStop;
         if (DirectionType == 0) {
            TrailingStop = Bid - ATRValue * TrailingStopATRFactor;
         } else {
            TrailingStop = Ask + ATRValue * TrailingStopATRFactor;
         }

         if ((DirectionType == 0 && TrailingStop > OrderStopLoss()) ||
             (DirectionType == 1 && TrailingStop < OrderStopLoss())) {
            OrderModify(OrderTicket(), OrderOpenPrice(), TrailingStop, OrderTakeProfit(), 0, Blue);
         }
      }
   }
}
