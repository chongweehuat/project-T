//+------------------------------------------------------------------+
//|                                                     SimpleTF.mq5 |
//|                                  Copyright 2021, MetaQuotes Ltd. |
//|                                             https://www.mql5.com |
//+------------------------------------------------------------------+
#property copyright "Copyright 2021, MetaQuotes Ltd."
#property link      "https://www.mql5.com"
#property version   "1.00"
#include <Trade\Trade.mqh>
#include <Trade\SymbolInfo.mqh>
#include <Trade\AccountInfo.mqh>
#include <Trade\PositionInfo.mqh>
#include "comment.mqh"
#include "library.mqh"
CTrade            m_trade;                      
CSymbolInfo       m_symbol;                     
CAccountInfo      m_account;
CPositionInfo     m_position;
CComment          m_comment;

input string            Inp_remark="SRETF3 1.0 MT51"; //Remark
input bool              Inp_comment=false; //Comment
input bool              Inp_show_print= false; // Show print message

input bool              Inp_skip_auth = true; // Skip authorization for backtesting
input int               auth_request_interval = 300; 

input string            server_url = "http://172.19.0.15:86/TradesConfig.php"; //Server URL
input string            server_auth_url = "http://172.19.0.15:86/TradesAuth.php"; //Server Auth URL

input string            Inp_pair=""; //Pair
input string            Inp_dir="buy"; //Dir
input int               Inp_magic=0; //Magic Number
input int               Inp_magic2=0; //Magic Number 2

input int               Inp_equity = 30000; // Equity
input double            Inp_max_drawdown=-1; //Max draw down
input int               Inp_entry_lapse=50; 

input int               Inp_entry_d1_period=10;
input int               Inp_entry_d1_height1=0;
input int               Inp_entry_d1_height2=0;

input int               Inp_entry_h1_period=10;
input int               Inp_entry_h1_height1=0;
input int               Inp_entry_h1_height2=0;

input ENUM_TIMEFRAMES   Inp_entry_tf = PERIOD_M15; // Entry TF
input int               Inp_entry_period = 96; // Entry Period
input int               Inp_entry_height = 800; // Entry Height
input int               Inp_entry_pc=20; // Entry PC
input int               Inp_entry_reverse = 100; // Entry Reverse

input ENUM_TIMEFRAMES   Inp_tp_tf = PERIOD_M15; // TP TF
input int               Inp_tp_period = 96; // TP Period
input int               Inp_tp_height = 800; // TP Height
input int               Inp_tp_pc=20; // TP PC
input int               Inp_tp_reverse = 100; // TP Reverse
input int               Inp_tp_gap = 800; // TP Gap

input ENUM_TIMEFRAMES   Inp_re_tf = PERIOD_M15; // RE TF
input int               Inp_re_period = 96; // RE Period
input int               Inp_re_pc=20; // RE PC
input int               Inp_re_reverse = 100; // RE Reverse
input int               Inp_re_gap = 100; // RE Gap

// Authorization variables
struct AuthConfig {
   long auth_FT;
   long auth_AT;
   long auth_CP;
   long auth_SL;
   long auth_CL;
};
AuthConfig authConfig;
datetime last_auth_request_time = 0;

datetime last_entry=0;

//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
int OnInit()
  {
   if(Inp_pair!="" && Inp_pair!=Symbol()){
      Alert(Inp_pair+" not match "+Symbol());
      return(INIT_FAILED);
   }
        
   m_trade.SetExpertMagicNumber(Inp_magic);
   if(Inp_comment){
      m_comment.Create("c1",0,0);
      m_comment.SetFont("Lucida Console",12,false,1.3);
   }
   
   UpdateTradeConfig();
   RequestAuthorization(true);  
   
   compute(); 
   
//---
   return(INIT_SUCCEEDED);
  }
//+------------------------------------------------------------------+
//| Expert deinitialization function                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
  {

   
  }
//+------------------------------------------------------------------+
//| Expert tick function                                             |
//+------------------------------------------------------------------+
void OnTick()
  {
//---
   compute();   
  }
//+------------------------------------------------------------------+
void compute(){
   RequestAuthorization(); 
   
   m_symbol.Name(Symbol());
   m_symbol.RefreshRates();
   
   double nlot=0.01*m_account.Equity()/Inp_equity;
   nlot=round(nlot*100)/100;
   nlot=MathMax(0.01,nlot);
   
   int total_open_count=PositionsTotal();
   ulong aTickets[];
   ulong aTickets_SL[];
      
   int openCount=0;
   double openProfit=0;
   double openVolume=0;
      
   datetime openTime=0;
   
   double minOpenPrice=0;
   double maxOpenPrice=0;
   
   for(int i=0; i<total_open_count; i++){
      m_position.SelectByIndex(i);
      if(m_position.Magic()>=Inp_magic && m_position.Magic()<=Inp_magic2 && m_position.Symbol()==Symbol() && m_position.TypeDescription()==Inp_dir){
         openCount++;
         openProfit+=m_position.Profit();
         openVolume+=m_position.Volume();
            
         if(minOpenPrice==0)minOpenPrice=m_position.PriceOpen();
         if(maxOpenPrice==0)maxOpenPrice=m_position.PriceOpen();
         minOpenPrice=MathMin(m_position.PriceOpen(),minOpenPrice);
         maxOpenPrice=MathMax(m_position.PriceOpen(),maxOpenPrice);
                                      
         ArrayResize(aTickets_SL,ArraySize(aTickets_SL)+1);
         aTickets_SL[ArraySize(aTickets_SL)-1]=m_position.Ticket();
         if((m_position.Profit()>0.1)){               
            ArrayResize(aTickets,ArraySize(aTickets)+1);
            aTickets[ArraySize(aTickets)-1]=m_position.Ticket();
         }
                  
         openTime=m_position.Time();
      }
   }
     
   string tfcurDir;
   int    tfxCount=0;
   double tfcurPrice;
   double tfxHeight=0;
   double tfcurHeight=0;
   double tfxPercent=0;
   int    tfbottomPos;
   int    tftopPos; 
   double tfminPrice=0;
   double tfmaxPrice=0;
   int    tfxWidth=0;
   double tfavrgPrice=0;
   
   double reverse_height=0;
   double d1_height=0;
   double h1_height=0;
   
   double gap_re=0;
   double gap_tp=0;
   if(openCount==0){
      if((TimeCurrent() - last_entry)>=Inp_entry_lapse){
         hgpcAverage(Symbol(),PERIOD_D1,Inp_entry_d1_period,tfcurDir,tfxCount,tfcurPrice,tfxHeight,tfcurHeight,tfxPercent,tfbottomPos,tftopPos,tfminPrice,tfmaxPrice,tfxWidth,tfavrgPrice);
         d1_height=tfxHeight;
         if(tfxHeight>=Inp_entry_d1_height1 && tfxHeight<=Inp_entry_d1_height2){
            hgpcAverage(Symbol(),PERIOD_H1,Inp_entry_h1_period,tfcurDir,tfxCount,tfcurPrice,tfxHeight,tfcurHeight,tfxPercent,tfbottomPos,tftopPos,tfminPrice,tfmaxPrice,tfxWidth,tfavrgPrice);
            h1_height=tfxHeight;
            if(tfxHeight>=Inp_entry_h1_height1 && tfxHeight<=Inp_entry_h1_height2){
               hgpcAverage(Symbol(),Inp_entry_tf,Inp_entry_period,tfcurDir,tfxCount,tfcurPrice,tfxHeight,tfcurHeight,tfxPercent,tfbottomPos,tftopPos,tfminPrice,tfmaxPrice,tfxWidth,tfavrgPrice);
               if(tfxPercent>50){
                  if(tfcurDir=="down")reverse_height=(tfmaxPrice-m_symbol.Ask())/m_symbol.Point();
               }else{
                  if(tfcurDir=="up")reverse_height=(m_symbol.Bid()-tfminPrice)/m_symbol.Point(); 
               }
               
               if(Inp_dir=="buy"){
                  if(tfxHeight>=Inp_entry_height && tfxPercent<=Inp_entry_pc && reverse_height>=Inp_entry_reverse && tfcurDir=="up"){
                     if(authConfig.auth_FT){
                        m_trade.PositionOpen(Symbol(),ORDER_TYPE_BUY,nlot,m_symbol.Ask(),0,0,"");
                        last_entry = TimeCurrent();
                     }   
                  }
               }else{
                  if(tfxHeight>=Inp_entry_height && tfxPercent>=(100-Inp_entry_pc) && reverse_height>=Inp_entry_reverse && tfcurDir=="down"){
                     if(authConfig.auth_FT){
                        m_trade.PositionOpen(Symbol(),ORDER_TYPE_SELL,nlot,m_symbol.Bid(),0,0,"");
                        last_entry = TimeCurrent();
                     }   
                  }
               }
            }   
         }
      }   
   }else{
      if(ArraySize(aTickets)==0){
         if(Inp_dir=="buy"){
            gap_re=(minOpenPrice-m_symbol.Bid())/m_symbol.Point();
         }
         if(Inp_dir=="sell"){
            gap_re=(m_symbol.Ask()-maxOpenPrice)/m_symbol.Point();
         }
         if(gap_re>=Inp_re_gap && (TimeCurrent() - last_entry)>=Inp_entry_lapse){
            hgpcAverage(Symbol(),Inp_re_tf,Inp_re_period,tfcurDir,tfxCount,tfcurPrice,tfxHeight,tfcurHeight,tfxPercent,tfbottomPos,tftopPos,tfminPrice,tfmaxPrice,tfxWidth,tfavrgPrice);
            if(tfxPercent>50){
               if(tfcurDir=="down")reverse_height=(tfmaxPrice-m_symbol.Ask())/m_symbol.Point();
            }else{
               if(tfcurDir=="up")reverse_height=(m_symbol.Bid()-tfminPrice)/m_symbol.Point(); 
            }
            
            if(Inp_dir=="buy"){
               if(tfxPercent<=Inp_re_pc && reverse_height>=Inp_re_reverse && tfcurDir=="up"){
                  if(authConfig.auth_AT){
                     m_trade.PositionOpen(Symbol(),ORDER_TYPE_BUY,nlot,m_symbol.Ask(),0,0,"");
                     last_entry = TimeCurrent();
                  }   
               }
            }else{
               if(tfxPercent>=(100-Inp_re_pc) && reverse_height>=Inp_re_reverse && tfcurDir=="down"){
                  if(authConfig.auth_AT){
                     m_trade.PositionOpen(Symbol(),ORDER_TYPE_SELL,nlot,m_symbol.Bid(),0,0,"");
                     last_entry = TimeCurrent();
                  }   
               }
            }
         }
         if(openProfit<=(m_account.Equity()*Inp_max_drawdown*0.01)){
            if(authConfig.auth_CL){
               for(int t=0;t<ArraySize(aTickets_SL);t++){
                  m_trade.PositionClose(aTickets_SL[t],3);  
               }
            }
         }   
      }else{
         if(Inp_dir=="buy"){
            gap_tp=(m_symbol.Bid()-minOpenPrice)/m_symbol.Point();
         }
         if(Inp_dir=="sell"){
            gap_tp=(maxOpenPrice-m_symbol.Ask())/m_symbol.Point();
         }
         
         if(gap_tp>=Inp_tp_gap){
         
            hgpcAverage(Symbol(),Inp_tp_tf,Inp_tp_period,tfcurDir,tfxCount,tfcurPrice,tfxHeight,tfcurHeight,tfxPercent,tfbottomPos,tftopPos,tfminPrice,tfmaxPrice,tfxWidth,tfavrgPrice);
            if(tfxPercent>50){
               if(tfcurDir=="down")reverse_height=(tfmaxPrice-m_symbol.Ask())/m_symbol.Point();
            }else{
               if(tfcurDir=="up")reverse_height=(m_symbol.Bid()-tfminPrice)/m_symbol.Point(); 
            }
            
            if(Inp_dir=="buy"){
               if(tfxHeight>=Inp_tp_height && tfxPercent>=(100-Inp_tp_pc) && reverse_height>=Inp_tp_reverse && tfcurDir=="down"){
                  if(authConfig.auth_CP){
                     for(int t=0;t<ArraySize(aTickets);t++){
                        m_trade.PositionClose(aTickets[t],3);  
                     }
                  }
               }
            }
            if(Inp_dir=="sell"){
               if(tfxHeight>=Inp_tp_height && tfxPercent<=Inp_tp_pc && reverse_height>=Inp_tp_reverse && tfcurDir=="up"){
                  if(authConfig.auth_CP){
                     for(int t=0;t<ArraySize(aTickets);t++){
                        m_trade.PositionClose(aTickets[t],3);  
                     }
                  }
               }
            }
         }
      }
   }
   
   if(Inp_comment){
      m_comment.SetText(0,StringFormat(
      "%s %s %s Magic:%g-%g Lot:%g Profit: %g Count: %g Vol: %g",
      Inp_remark,Inp_pair,Inp_dir,Inp_magic,Inp_magic2,nlot,openProfit,openCount,openVolume
      ),clrWhite);
      
      if(openCount==0){
         m_comment.SetText(1,StringFormat(
         "d1H: %g - %g / %g h1H: %g - %g / %g",
         Inp_entry_d1_height1,Inp_entry_d1_height2,d1_height,Inp_entry_h1_height1,Inp_entry_h1_height2,h1_height
         ),clrWhite);
         
         m_comment.SetText(2,StringFormat(
         "H: %g / %g PC: %g / %g Rvs: %g / %g Dir: %s",
         Inp_entry_height,tfxHeight,Inp_entry_pc,tfxPercent,Inp_entry_reverse,reverse_height,tfcurDir
         ),clrWhite);
      }else{
         if(ArraySize(aTickets)==0){
            m_comment.SetText(1,StringFormat(
            "RE Gap: %g / %g PC: %g / %g Rvs: %g / %g Dir: %s",
            Inp_re_gap,gap_re,Inp_re_pc,tfxPercent,Inp_re_reverse,reverse_height,tfcurDir
            ),clrWhite);
         }else{
            m_comment.SetText(1,StringFormat(
            "TP Gap: %g / %g H: %g / %g PC: %g / %g Rvs: %g / %g Dir: %s",
            Inp_tp_gap,gap_tp,Inp_tp_height,tfxHeight,(100-Inp_tp_pc),tfxPercent,Inp_tp_reverse,reverse_height,tfcurDir
            ),clrWhite);
         }
      }
      
      m_comment.SetText(3,StringFormat(
      "Min: %g Max: %g",
      tfminPrice,tfmaxPrice
      ),clrWhite);
         
     
      m_comment.SetText(5,StringFormat(
      "Auth Config: FT: %g AT: %g CL: %g CP: %g SL: %g",
      authConfig.auth_FT,authConfig.auth_AT,authConfig.auth_CL,authConfig.auth_CP,authConfig.auth_SL
      ),clrWhite);
      
      if(!Inp_skip_auth){
         m_comment.SetText(6,StringFormat(
         "Auth request : %g / %g",
         auth_request_interval,TimeCurrent() - last_auth_request_time
         ),clrWhite);
      }
      
      m_comment.Show(); 
      
      if(tfmaxPrice>0){
         ObjectCreate(0,"tfmaxPrice",OBJ_HLINE,0,0,tfmaxPrice);
         ObjectSetDouble(0,"tfmaxPrice",OBJPROP_PRICE,tfmaxPrice);
         ObjectSetInteger(0,"tfmaxPrice",OBJPROP_COLOR,clrBlue);
      }
      
      if(tfminPrice>0){
         ObjectCreate(0,"tfminPrice",OBJ_HLINE,0,0,tfminPrice);
         ObjectSetDouble(0,"tfminPrice",OBJPROP_PRICE,tfminPrice);
         ObjectSetInteger(0,"tfminPrice",OBJPROP_COLOR,clrGreen);
      }
   }
}   