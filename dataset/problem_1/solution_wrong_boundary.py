# Sai dữ liệu biên: Không xử lý được số lớn hoặc sai logic so sánh
import sys

def main():
    input_data = sys.stdin.read().split()
    if len(input_data) >= 2:
        a = int(input_data[0])
        b = int(input_data[1])
        # Cố tình sai logic ở miền giá trị lớn (tràn số hoặc ép cứng kết quả)
        if abs(a) > 1000 or abs(b) > 1000:
            print(0) 
        else:
            print(a + b)

if __name__ == '__main__':
    main()