# Đặc tả module Flash Sale

## Mục tiêu

`PeakGear_FlashSale` cho phép admin cấu hình các đợt flash sale theo thời gian, gắn nhiều sản phẩm vào từng đợt, áp giá giảm theo phần trăm trong thời gian sale, giới hạn số lượng sale và giới hạn mua theo đơn hàng/khách hàng. Module hiển thị flash sale trên trang chủ, áp giá vào catalog/cart trong runtime và trừ số lượng flash sale sau khi đặt hàng thành công.

Tài liệu này mô tả theo implementation hiện có trong `src/app/code/PeakGear/FlashSale`, không phải bản thiết kế lý tưởng chưa triển khai.

## Phạm vi

### Có trong module

- Admin CRUD flash sale tại menu **Catalog > Flash Sales**.
- Mỗi flash sale có tiêu đề, trạng thái bật/tắt, thời gian bắt đầu và kết thúc.
- Mỗi flash sale có nhiều sản phẩm, mỗi sản phẩm có:
  - phần trăm giảm giá;
  - số lượng flash sale;
  - số lượng đã bán;
  - giới hạn tối đa mỗi khách hàng;
  - giới hạn tối đa mỗi đơn hàng.
- Trang chủ hiển thị flash sale đang diễn ra hoặc sắp diễn ra trong vòng 72 giờ.
- Giá catalog/cart được giảm trong thời gian flash sale còn hiệu lực và còn số lượng.
- Cart và checkout chặn mua vượt số lượng còn lại, vượt giới hạn mỗi đơn, vượt giới hạn mỗi khách.
- Sau khi order được place, module tăng `sold_qty` cho item flash sale tương ứng.

### Không nằm trong module hiện tại

- Không dùng Magento `catalogrule` hoặc `salesrule`; giá được áp bằng plugin và quote item custom price.
- Không có cron tự động bật/tắt sale; trạng thái hiệu lực được tính theo `start_at`, `end_at`, `is_active`.
- Không có reservation/lock số lượng trước khi đặt hàng; số lượng chỉ tăng sau `sales_order_place_after`.
- Không có UI riêng cho product detail page ngoài tác động giá final price chung.
- Không có rule chống cùng một sản phẩm xuất hiện đồng thời ở nhiều flash sale khác nhau; khi trùng, runtime chọn item active có phần trăm giảm cao nhất.

## Thành phần chính

| Thành phần | File | Trách nhiệm |
|---|---|---|
| Module declaration | `etc/module.xml` | Khai báo phụ thuộc `Magento_Catalog`, `Magento_Checkout`, `Magento_Sales`. |
| Database schema | `etc/db_schema.xml` | Tạo bảng `peakgear_flash_sale` và `peakgear_flash_sale_item`. |
| Admin routes/menu/ACL | `etc/adminhtml/*.xml`, `etc/acl.xml` | Đăng ký route `peakgear_flashsale`, menu admin và quyền truy cập. |
| Admin controller | `Controller/Adminhtml/Sale/*` | List, create, edit, save, delete flash sale. |
| Product search API | `Controller/Adminhtml/Product/Search.php` | Ajax search sản phẩm theo tên, SKU hoặc ID trong admin form. |
| Admin templates | `view/adminhtml/templates/sale/*.phtml` | Giao diện danh sách và form cấu hình flash sale. |
| Home block/template | `Block/Home/FlashSale.php`, `view/frontend/templates/home/flash-sale.phtml` | Lấy sale group hợp lệ và render section trang chủ. |
| Pricing service | `Model/FlashSaleService.php` | Tìm flash sale active cho product, tính giá giảm, validate số lượng, tăng `sold_qty`. |
| Pricing plugins | `Plugin/ProductPricePlugin.php`, `Plugin/FinalPricePlugin.php` | Override final price trong catalog pricing runtime. |
| Cart observers | `Observer/ApplyCartItemFlashSale.php`, `RefreshQuotePrices.php`, `ValidateCartUpdate.php` | Áp giá custom vào quote item và validate cập nhật cart. |
| Order observers | `Observer/ValidateQuoteBeforeSubmit.php`, `DecrementSaleQuantity.php` | Validate lần cuối trước submit và tăng số đã bán sau khi order place. |
| Cart view model | `ViewModel/CartItemFlashSale.php` | Cung cấp thông tin flash sale để cart template hiển thị. |

## Mô hình dữ liệu

### `peakgear_flash_sale`

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `sale_id` | int identity | Khóa chính của đợt flash sale. |
| `title` | varchar(255) | Tên hiển thị trên admin và frontend. |
| `is_active` | smallint | Cờ bật/tắt thủ công. |
| `start_at` | timestamp UTC | Thời điểm bắt đầu. |
| `end_at` | timestamp UTC | Thời điểm kết thúc. |
| `created_at` | timestamp | Thời điểm tạo. |
| `updated_at` | timestamp | Thời điểm cập nhật. |

Index chính: `PEAKGEAR_FLASH_SALE_ACTIVE_TIME(is_active, start_at, end_at)` để lọc sale active/upcoming.

### `peakgear_flash_sale_item`

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `item_id` | int identity | Khóa chính của dòng sản phẩm flash sale. |
| `sale_id` | int | FK về `peakgear_flash_sale.sale_id`, xóa cascade theo sale. |
| `product_id` | int | Magento product entity ID. |
| `discount_percent` | decimal(5,2) | Phần trăm giảm, được clamp từ 0 đến 100 khi save. |
| `qty_limit` | int | Tổng số lượng dành cho flash sale. |
| `sold_qty` | int | Số lượng đã bán trong flash sale. |
| `max_per_customer` | int | Giới hạn theo khách; `0` nghĩa là không giới hạn. |
| `max_per_order` | int | Giới hạn theo đơn; `0` nghĩa là không giới hạn. |
| `created_at` | timestamp | Thời điểm tạo dòng. |

Ràng buộc unique: `(sale_id, product_id)`, nghĩa là trong cùng một flash sale một sản phẩm chỉ có một dòng cấu hình.

## Luồng admin

1. Admin vào **Catalog > Flash Sales**.
2. Màn danh sách đọc collection `PeakGear\FlashSale\Model\ResourceModel\Sale\Collection` và hiển thị theo `sale_id DESC`.
3. Khi tạo/sửa, admin nhập:
   - `title`;
   - `is_active`;
   - `start_at`, `end_at` theo timezone cấu hình store, định dạng `YYYY-MM-DD HH:mm`;
   - danh sách sản phẩm và các giới hạn.
4. Product search gọi `peakgear_flashsale/product/search?q=...`, trả về tối đa 12 sản phẩm theo tên, SKU hoặc ID.
5. Khi save:
   - `start_at`, `end_at` được convert từ timezone store sang UTC;
   - sale được lưu trước;
   - toàn bộ items cũ của sale bị xóa;
   - items từ request được tạo lại.

Hệ quả quan trọng: vì save form đang thay thế toàn bộ item list, việc edit một sale đã chạy cần cẩn thận với `sold_qty`; admin form cho phép nhập lại `sold_qty` để bảo toàn hoặc điều chỉnh thủ công.

## Luồng hiển thị trang chủ

Flash sale được gắn vào homepage tại `src/app/design/frontend/PeakGear/climbing/Magento_Cms/layout/cms_index_index.xml`, sau section features và trước category section.

`Block\Home\FlashSale::getSaleGroups()` lấy các item thỏa:

- sale đang bật (`is_active = 1`);
- `end_at > now`;
- `start_at <= now + 72h`;
- `qty_limit - sold_qty > 0`.

Sau đó group theo `sale_id`, sort theo `sale.start_at ASC`, trong mỗi sale sort item theo `discount_percent DESC`.

Trạng thái hiển thị:

| Điều kiện | Status | Countdown |
|---|---|---|
| `start_at <= now < end_at` | `active` / "Đang diễn ra" | Đếm ngược tới `end_at`. |
| `now < start_at <= now + 72h` | `upcoming` / "Sắp diễn ra" | Đếm ngược tới `start_at`. |
| `start_at > now + 72h` hoặc `end_at <= now` | Không render | Không có countdown. |

Frontend chỉ hiện nút thêm giỏ khi sale đang `active` và product available. Upcoming sale vẫn hiển thị sản phẩm và giá flash sale dự kiến nhưng không cho add-to-cart từ box flash sale.

## Luồng tính giá

`FlashSaleService::getActiveItemForProduct(productId)` là điểm quyết định một product có flash sale active hay không. Điều kiện:

- product nằm trong `peakgear_flash_sale_item`;
- sale `is_active = 1`;
- `start_at <= now`;
- `end_at > now`;
- `qty_limit - sold_qty > 0`.

Nếu có nhiều item active cho cùng product, service chọn dòng có `discount_percent` cao nhất.

Giá flash sale:

```text
discounted_price = round(base_price * (100 - discount_percent) / 100, 4)
```

Trong catalog pricing, plugin trả về:

```text
min(current_final_price, discounted_price)
```

Cách này đảm bảo nếu Magento đã có giá thấp hơn flash sale thì module không làm giá tăng lên.

Trong cart, observer `ApplyCartItemFlashSale` và `RefreshQuotePrices` set:

- `custom_price`;
- `original_custom_price`;
- quote item option `peakgear_flash_sale_item_id`;
- `product->setIsSuperMode(true)`.

Khi sale hết hiệu lực, `RefreshQuotePrices` gỡ custom price và option khỏi quote item có option flash sale cũ.

## Luồng validate số lượng

`FlashSaleService::validateQty()` trả về message lỗi nếu product đang thuộc flash sale active và request vi phạm:

1. `requested_qty > remaining_qty`, với `remaining_qty = qty_limit - sold_qty`.
2. `max_per_order > 0` và `requested_qty > max_per_order`.
3. `max_per_customer > 0` và `ordered_qty + requested_qty > max_per_customer`.

`ordered_qty` được tính từ `sales_order_item` join `sales_order`, chỉ tính đơn trong khoảng `sale.start_at <= order.created_at < sale.end_at`, bỏ qua order state `canceled`, `closed`. Với khách đã đăng nhập dùng `customer_id`; với guest dùng `customer_email`.

Các điểm validate:

| Event | Observer | Mục đích |
|---|---|---|
| `checkout_cart_product_add_after` | `ApplyCartItemFlashSale` | Validate khi thêm sản phẩm vào cart; nếu lỗi thì remove quote item vừa thêm. |
| `checkout_cart_update_items_before` | `ValidateCartUpdate` | Chặn tăng/đổi số lượng trong cart vượt giới hạn. |
| `sales_model_service_quote_submit_before` | `ValidateQuoteBeforeSubmit` | Validate lần cuối trước khi submit order. |

## Luồng trừ số lượng

Sau khi order place, `DecrementSaleQuantity` chạy ở event `sales_order_place_after`.

Với từng visible order item:

1. tìm flash sale active cho `product_id`;
2. gọi `incrementSoldQty(item_id, ceil(qty_ordered))`;
3. update database bằng:

```sql
sold_qty = LEAST(qty_limit, sold_qty + ordered_qty)
```

Module không giảm Magento stock trực tiếp; stock thật vẫn do Magento inventory xử lý. `sold_qty` chỉ là bộ đếm quota flash sale.

## Tích hợp cart UI

Cart item template đọc `flash_sale_view_model` từ layout `PeakGear_Cart/view/frontend/layout/checkout_cart_item_renderers.xml`. Khi có flash sale active, cart hiển thị:

- phần trăm giảm;
- số lượng flash sale còn lại;
- giới hạn tối đa mỗi đơn nếu có.

Thông tin này là hiển thị bổ trợ; validate thật vẫn nằm ở observer/server-side.

## Quy tắc nghiệp vụ

- `is_active = 0` luôn vô hiệu hóa sale, dù đang trong khoảng thời gian.
- `qty_limit = 0` nghĩa là không còn quota flash sale, item không được active. Trường này khác với `max_per_customer = 0` và `max_per_order = 0`, hai trường đó nghĩa là không giới hạn.
- Sale hết hạn không render ở homepage và không áp giá runtime.
- Sale chưa bắt đầu chỉ render ở homepage nếu còn dưới hoặc bằng 72 giờ nữa.
- Trong cùng một sale không cho trùng product nhờ unique constraint.
- Giữa nhiều sale khác nhau vẫn có thể trùng product; runtime chọn discount cao nhất nếu nhiều sale cùng active.

## Rủi ro và giới hạn hiện tại

| Rủi ro | Tác động | Khuyến nghị |
|---|---|---|
| Không có reservation/row lock khi checkout đồng thời. | Hai order cùng lúc có thể cùng pass validate trước khi `sold_qty` tăng; update sau đó clamp về `qty_limit`, nhưng vẫn có khả năng bán vượt quota logic. | Thêm transaction/conditional update ở bước submit: chỉ tăng khi `sold_qty + qty <= qty_limit`, nếu không đủ thì throw lỗi. |
| Save admin xóa và tạo lại toàn bộ item. | Có thể mất `item_id`, option cũ trong quote không còn map đúng nếu sale đang chạy. | Khi update sale đang active, nên update diff theo product thay vì replace toàn bộ hoặc cảnh báo admin. |
| `sold_qty` chỉ tăng sau order place, không tự giảm khi order cancel/refund. | Quota flash sale không được trả lại sau hủy đơn. | Thêm observer cho cancel/credit memo nếu nghiệp vụ cần hoàn quota. |
| Guest limit dựa vào email. | Khách có thể dùng email khác để vượt `max_per_customer`. | Chấp nhận như giới hạn mềm hoặc bổ sung rule theo account/phone nếu checkout có dữ liệu. |
| Giá flash sale dựa trên `product->getPrice()` làm base khi tính discount. | Nếu product có special price/catalog rule, phần trăm giảm không tính trên final price hiện tại; plugin vẫn lấy `min` để tránh tăng giá. | Chốt nghiệp vụ: giảm trên giá gốc hay final price. Nếu giảm trên final price, đổi base price trong service. |
| Không có test tự động trong module. | Regression pricing/cart/order khó phát hiện. | Bổ sung integration test cho active/upcoming/expired sale, validate quota và decrement `sold_qty`. |

## Checklist kiểm thử đề xuất

- [ ] Admin tạo flash sale mới với một sản phẩm, thời gian đang active, `qty_limit > 0`.
- [ ] Trang chủ hiển thị section flash sale trước category section, có countdown tới giờ kết thúc.
- [ ] Sale sắp diễn ra trong 72 giờ hiển thị countdown tới giờ bắt đầu và không hiện nút add-to-cart trong box.
- [ ] Sale sắp diễn ra sau hơn 72 giờ không render ở homepage.
- [ ] Product trong sale active hiển thị final price đã giảm ở listing/PDP/cart.
- [ ] Add-to-cart với số lượng vượt `remaining_qty` bị chặn.
- [ ] Update cart vượt `max_per_order` bị chặn.
- [ ] Khách đã mua đủ `max_per_customer` trong cùng sale bị chặn mua thêm.
- [ ] Order thành công làm tăng `sold_qty` nhưng không vượt `qty_limit`.
- [ ] Khi sale hết hạn, cart collect totals gỡ custom price khỏi quote item cũ.
- [ ] Khi product hết quota flash sale, product không còn render trong homepage flash sale.

## Hướng vận hành

Sau khi thay đổi module/schema trên môi trường deploy, chạy:

```bash
php bin/magento setup:upgrade
php bin/magento cache:flush
php bin/magento indexer:reindex
```

Khi thay đổi template hoặc LESS của module:

```bash
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

## Câu hỏi thiết kế đã chốt từ code hiện tại

- Flash sale là module riêng, không mở rộng Magento promotion rule.
- Một sale có nhiều sản phẩm; một product có thể nằm ở nhiều sale khác nhau.
- Sale active được quyết định runtime theo thời gian UTC đã normalize từ timezone store.
- Giới hạn quota flash sale độc lập với tồn kho Magento.
- Cart/checkout phải validate server-side, không phụ thuộc vào UI.

## Câu hỏi còn cần xác nhận nếu phát triển tiếp

- Nếu order bị hủy, quota flash sale có được hoàn lại không?
- Nếu một product đồng thời có special price và flash sale, phần trăm giảm nên tính trên giá gốc hay final price sau promotion khác?
- Có cần cấm product trùng giữa các flash sale có thời gian overlap không?
- Có cần khóa quota theo customer ngay khi add-to-cart hay chỉ khi place order?
- Có cần báo cáo admin về doanh thu/số lượng bán theo từng flash sale không?
     