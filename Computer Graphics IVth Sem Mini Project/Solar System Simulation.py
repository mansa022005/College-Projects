import turtle
import math
import random

# Screen setup
screen = turtle.Screen()
screen.bgcolor("black")
screen.title("Solar System Simulation")

screen.tracer(0)

# Pause control
running = True
def toggle():
    global running
    running = not running

screen.listen()
screen.onkey(toggle, "space")

# Sun
sun = turtle.Turtle()
sun.shape("circle")
sun.color("orange")
sun.shapesize(2.5)
sun.penup()

# Stars background
stars = turtle.Turtle()
stars.hideturtle()
stars.speed(0)
stars.penup()

for _ in range(80):
    x = random.randint(-400, 400)
    y = random.randint(-300, 300)
    stars.goto(x, y)
    stars.dot(random.randint(2, 4), "white")

# Orbit function
def draw_orbit(radius):
    orbit = turtle.Turtle()
    orbit.hideturtle()
    orbit.color("white")
    orbit.penup()
    orbit.goto(0, -radius)
    orbit.pendown()
    orbit.circle(radius)

# Planet function
def create_planet(color, size, name):
    p = turtle.Turtle()
    p.shape("circle")
    p.color(color)
    p.shapesize(size)
    p.penup()

    label = turtle.Turtle()
    label.hideturtle()
    label.color("white")
    label.penup()

    return p, label

# Create planets + labels
mercury, m_label = create_planet("gray", 0.4, "Mercury")
venus, v_label   = create_planet("orange", 0.6, "Venus")
earth, e_label   = create_planet("blue", 0.7, "Earth")
mars, ma_label   = create_planet("red", 0.5, "Mars")
jupiter, j_label = create_planet("brown", 1.1, "Jupiter")
saturn, s_label  = create_planet("gold", 1.0, "Saturn")
uranus, u_label  = create_planet("lightblue", 0.8, "Uranus")
neptune, n_label = create_planet("darkblue", 0.8, "Neptune")

# Orbit radii
r = [60, 90, 120, 150, 190, 230, 270, 310]

# Draw orbits
for i in r:
    draw_orbit(i)

#Moon
moon = turtle.Turtle()
moon.shape("circle")
moon.color("white")
moon.shapesize(0.2)
moon.penup()

# Saturn rings
ring = turtle.Turtle()
ring.hideturtle()
ring.color("white")
ring.penup()

moon_label = turtle.Turtle()
moon_label.hideturtle()
moon_label.color("white")
moon_label.penup()

moon_orbit_radius = turtle.Turtle()
moon_orbit_radius.hideturtle()
moon_orbit_radius.color("white")
moon_orbit_radius.penup()

angle = 0

while True:
    if running:
        
        mercury.goto(r[0]*math.cos(angle*1.2), r[0]*math.sin(angle*1.2))
        venus.goto(r[1]*math.cos(angle*0.9),   r[1]*math.sin(angle*0.9))
        earth_x = r[2]*math.cos(angle*0.7)
        earth_y = r[2]*math.sin(angle*0.7)
        earth.goto(earth_x, earth_y)
        moon.goto(earth_x + 20*math.cos(angle*2.5), earth_y + 20*math.sin(angle*2.5))
        moon_orbit_radius.clear()
        moon_orbit_radius.goto(earth_x, earth_y-20)
        moon_orbit_radius.pendown()
        moon_orbit_radius.circle(20)
        moon_orbit_radius.penup()   
        mars.goto(r[3]*math.cos(angle*0.5),    r[3]*math.sin(angle*0.5))
        jupiter.goto(r[4]*math.cos(angle*0.3), r[4]*math.sin(angle*0.3))
        saturn.goto(r[5]*math.cos(angle*0.25), r[5]*math.sin(angle*0.25))
        uranus.goto(r[6]*math.cos(angle*0.2),  r[6]*math.sin(angle*0.2))
        neptune.goto(r[7]*math.cos(angle*0.15),r[7]*math.sin(angle*0.15))

        # Labels
        def label_planet(label, planet, name):
            label.goto(planet.xcor()+10, planet.ycor()+10)
            label.clear()
            label.write(name, font=("Arial", 10, "normal"))

        label_planet(m_label, mercury, "Mercury")
        label_planet(v_label, venus, "Venus")
        label_planet(e_label, earth, "Earth")
        label_planet(moon_label, moon, "Moon")
        label_planet(ma_label, mars, "Mars")
        label_planet(j_label, jupiter, "Jupiter")
        label_planet(s_label, saturn, "Saturn")
        label_planet(u_label, uranus, "Uranus")
        label_planet(n_label, neptune, "Neptune")

        # Saturn ring
        ring.clear()
        ring.goto(saturn.xcor(), saturn.ycor()-15)
        ring.pendown()
        ring.circle(18)
        ring.penup()


        angle += 0.01 

    screen.update()