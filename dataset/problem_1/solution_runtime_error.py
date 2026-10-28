# Gây lỗi khi chạy (Runtime Error - Chia cho 0 hoặc truy xuất sai index)
import sys

def main():
    x = 1 / 0  # ZeroDivisionError gây Runtime Error (RE)
    print(x)

if __name__ == '__main__':
    main()