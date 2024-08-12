<?php
// Đường dẫn tới RSS feed của VnExpress
$rss_feed_url = 'https://vnexpress.net/rss/the-thao.rss';

// Tải RSS feed
$rss = simplexml_load_file($rss_feed_url);

if ($rss) {
    // Lấy mục tin tức đầu tiên
    $item = $rss->channel->item[0];
    echo '<div class="rss-feed">';
    
    echo '<div class="rss-item">';
    echo '<h6 class="rss-title"><a href="' . $item->link . '" target="_blank">' . $item->title . '</a></h6>';
    
    // Sử dụng DOMDocument để phân tích phần mô tả
    $doc = new DOMDocument();
    @$doc->loadHTML($item->description); // Sử dụng @ để ẩn thông báo lỗi nếu có

    $imageTags = $doc->getElementsByTagName('img');
    if ($imageTags->length > 0) {
        // Lấy hình ảnh đầu tiên
        $imgSrc = $imageTags->item(0)->getAttribute('src');
        echo '<div class="rss-description"><img src="' . $imgSrc . '" alt="Image"></div>';
    } else {
        echo '<div class="rss-description">Không có hình ảnh.</div>';
    }
    
    echo '</div>';
    echo '</div>';
} else {
    echo 'Không thể tải RSS feed.';
}
?>
