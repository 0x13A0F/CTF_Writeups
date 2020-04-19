import nclib,os,time
nc = nclib.Netcat(("bequick.challs.shellmates.club",1337))
res = nc.recv_until(b"Decoded string: ")
# print the qrcode
print(res.decode("utf-8"))
# sleep and take screenshot
time.sleep(0.1)
os.system("import -window root qr.png")
# decode qrcode
os.system("java -cp 'zxing/core-3.3.4.jar:zxing/javase-3.3.4.jar:zxing/jcommander-1.72.jar' com.google.zxing.client.j2se.CommandLineRunner qr.png | head -3 | tail -1 > decoded.txt")
with open("decoded.txt","r") as f: decoded=f.read()
# send decoded output
nc.send(decoded.encode("utf-8"))
print(nc.recv())
