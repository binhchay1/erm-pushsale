# ERM SaleOps — Kiến trúc (enterprise)

## Layered architecture

```
HTTP (Inertia)
    ↓
Controllers (thin) + Concerns/InteractsWithReportFilters
    ↓
Services (use cases) — Reports/*, Operations/*, Inventory/*
    ↓
Repository (OrderRepositoryInterface → EloquentOrderRepository)
    ↓
Models + Scopes (Order::applyReportFilter)
```

## Design patterns

| Pattern | Vị trí | Mục đích |
|---------|--------|----------|
| **Repository** | `Contracts/Repositories`, `Repositories/` | Trừu tượng truy vấn Order, dễ test/thay DB |
| **DTO** | `Data/ReportFilterData`, `MetricPairData` | Immutable filter & metric transport |
| **Service / Use case** | `Services/Reports/*`, `Operations/*` | Nghiệp vụ từng màn, một trách nhiệm |
| **Presenter** | `OrderOperationPresenter` | Map Model → JSON cho React |
| **Strategy** | `RevenueMetricsCalculator` | Công thức (1)–(19) dùng chung MKT & Sale |
| **Template Method** | `RevenueReportService` | `forMarketers` / `forSales` cùng pipeline |
| **Factory (request)** | `ReportFilterData::fromRequest` | Chuẩn hóa query + scope sales |
| **Dependency Injection** | `AppServiceProvider::register` | Bind interface → implementation |

## Phân quyền

- Middleware `role:admin` | `role:sales`
- `ReportFilterData::fromRequest` ép `sale_id` khi user là sales
- Repository không bypass — filter tại DTO

## Frontend

- **Pages** = route targets (`pages/Admin/...`, `pages/Sales/...`)
- **Components** = tái sử dụng (`reports/`, `operations/`, `charts/`)
- **Hooks** = `useReportSearch`, `useRealtimeDashboard`
- **Providers** = `ThemeProvider`

## Mở rộng

1. Thêm màn → Service mới + Controller invokable + Page JSX
2. Thêm field → migration + `DATA-MODEL.md` + Presenter/Service
3. Event realtime → `Event` + broadcast trên `Order` lifecycle
