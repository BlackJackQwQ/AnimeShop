content = open('init.sql', 'r', encoding='utf-8').read()
old = 's:14:\\\\"anime-shop.php\\\\"'
new = 's:24:\\\\"anime-shop/anime-shop.php\\\\"'
result = content.replace(old, new)
count = content.count(old)
open('init.sql', 'w', encoding='utf-8').write(result)
print('Replaced', count, 'occurrences of active_plugins path')
