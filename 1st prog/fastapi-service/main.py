"""Local baseline monitoring API for the Smart Stock sales demo.

This does not train a machine-learning model. It calculates a transparent
30-day baseline from recent completed sales; a trained model can later replace
the forecast while keeping the same PHP integration.
"""

from fastapi import FastAPI
from pydantic import BaseModel, Field

app = FastAPI(title="Smart Stock Monitoring Demo")


class ProductSignal(BaseModel):
    product_id: int = Field(gt=0)
    product_name: str
    current_stock: int = Field(ge=0)
    sold_30d: int = Field(ge=0)
    sold_90d: int = Field(ge=0)
    age_days: int = Field(ge=0)
    days_since_last_sale: int | None = Field(default=None, ge=0)


class AnalysisRequest(BaseModel):
    products: list[ProductSignal]


@app.get("/")
def home() -> dict[str, str]:
    return {"message": "Smart Stock monitoring API is running"}


def analyze_product(product: ProductSignal) -> dict:
    # Recent sales repeated for the next 30 days: a baseline, not an AI model.
    forecast_sales_30d = product.sold_30d
    projected_stock_30d = max(0, product.current_stock - forecast_sales_30d)
    shortage_30d = max(0, forecast_sales_30d - product.current_stock)

    if product.current_stock > 0 and product.age_days >= 90 and product.sold_90d == 0:
        risk = "possible_deadstock"
        reason = "Stock remains and no sale was recorded in the last 90 days."
    elif product.current_stock > 0 and product.age_days >= 30 and product.sold_30d <= 2:
        risk = "slow_moving"
        reason = "Only 0-2 units were sold in the last 30 days."
    elif forecast_sales_30d > product.current_stock:
        risk = "stockout_risk"
        reason = "The 30-day sales baseline is larger than current stock."
    elif product.age_days < 30:
        risk = "collecting_history"
        reason = "This product has fewer than 30 days of sales history."
    else:
        risk = "active"
        reason = "Recent sales were recorded."

    return {
        "product_id": product.product_id,
        "forecast_sales_30d": forecast_sales_30d,
        "projected_stock_30d": projected_stock_30d,
        "shortage_30d": shortage_30d,
        "risk": risk,
        "reason": reason,
        "method": "recent_30_day_sales_baseline",
    }


@app.post("/analyze")
def analyze(request: AnalysisRequest) -> dict:
    return {"products": [analyze_product(p) for p in request.products]}
