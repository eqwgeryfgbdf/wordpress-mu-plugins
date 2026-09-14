# WordPress Must-Use Plugins（站點專用）

這組 **mu-plugins** 來自 [RoyLung 的部落格](https://wordpress.yuchenglung.com) 的站內客製，**不是**一般可直接套用到任意主題的獨立商品外掛。

## 來源標註 / Source Attribution

| 項目 | 內容 |
|------|------|
| 站點 | https://wordpress.yuchenglung.com |
| 作者 | Roy（GitLab [@roylung](https://gitlab.com/roylung)） |
| 原始路徑 | `wp-content/mu-plugins/` |
| 主題依賴 | 主要搭配 **Puock**；部分檔案會改前台版面／首頁行為 |
| 授權 | 以各檔案註解為準；公開分享供學習與備份 |

> 若你要在別的站使用，請先閱讀各檔說明，並自行調整路徑、選單 ID、分類 slug、主題 class 等站點綁定設定。

## 包含檔案

| 檔案 | 說明 |
|------|------|
| `classic-post-editor.php` | 強制使用傳統文章編輯畫面 |
| `google-sitemap-bridge.php` | 讓 Google Search Console 能抓到 PATHINFO 安裝的 Rank Math sitemap |
| `homepage-intro.php` | 首頁個人簽名／介紹區塊 |
| `homepage-sidebars.php` + `homepage-sidebars/` | 首頁左右側欄（不改主題檔） |
| `post-related-reading.php` | 單篇文章系列導覽與相關閱讀 |
| `site-fluid-layout.php` | 依頁面類型調整內容寬度 |
| `site-footer.php` | 美化頁尾（不改主題檔） |
| `site-hardening.php` | 本機 Synology WordPress 強化（站點專用） |
| `stock-listing-focus.php` | 首頁只顯示最新交易日台股文；舊文在台股總覽頁 |
| `locale-phrase-convert/` + `locale-phrase-convert.php.disabled` | 用語轉換相關資產（目前主檔為 disabled） |

## 安裝

Must-use 外掛會自動載入，不需後台啟用：

1. 將檔案放到目標站的 `wp-content/mu-plugins/`
2. 確認 PHP 語法與主題相容後再上線

## 注意

- `site-hardening.php` 針對本站 Synology 環境，**不建議**原樣套用到其他主機。
- 部署前請用 `php -l` 檢查，避免語法錯誤拖垮整站。
