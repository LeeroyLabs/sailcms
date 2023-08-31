import psutil

try:
    ram_info = psutil.virtual_memory()
    print(ram_info.total)
    print(ram_info.available)
    print(ram_info.used)
    print(ram_info.percent)
except FileNotFoundError:
    print("Ram info not available on this system")

try:
    disk_info = psutil.disk_usage("/")
    print(disk_info.total)
    print(disk_info.used)
    print(disk_info.free)
    print(disk_info.percent)
except FileNotFoundError:
    print("Disk info not available on this system")

try:
    cpu_info = psutil.cpu_percent()
    cpu_count = psutil.cpu_count()
    print(cpu_info)
    print(cpu_count)
except FileNotFoundError:
    print("Disk info not available on this system")

boot_time = psutil.boot_time()
print(boot_time)